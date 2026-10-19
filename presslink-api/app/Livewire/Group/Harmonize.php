<?php

namespace App\Livewire\Group;

use App\Actions\Group\SyncCatalog;
use App\Enums\ActivityAction;
use App\Livewire\Concerns\ShowsToasts;
use App\Livewire\Group\Concerns\InteractsWithGroup;
use App\Models\ActivityLog;
use App\Models\Pressing;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Harmoniser le groupe : recopier les tarifs d'un pressing modèle vers les
 * autres, et les paramètres communs (logo, horaires, présentation,
 * affichage des prix aux clients).
 */
class Harmonize extends Component
{
    use InteractsWithGroup, ShowsToasts;

    public const SETTINGS = [
        'logo' => 'Logo',
        'opening_hours' => 'Horaires d\'ouverture',
        'description' => 'Présentation du pressing',
        'show_pricing_to_customers' => 'Affichage des tarifs aux clients',
    ];

    public string $catalogSource = '';

    /** @var list<string> */
    public array $catalogTargets = [];

    public string $settingsSource = '';

    /** @var list<string> */
    public array $settingsTargets = [];

    /** @var list<string> */
    public array $settingsFields = ['logo', 'opening_hours'];

    public function mount(): void
    {
        // Le pressing le plus complet sert de modèle par défaut.
        $model = Pressing::whereIn('id', $this->groupPressingIds())
            ->withCount(['services' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('services_count')
            ->orderBy('name')
            ->first();

        $this->catalogSource = (string) ($model?->id ?? '');
        $this->settingsSource = $this->catalogSource;
    }

    private function groupPressing(string $id): Pressing
    {
        abort_unless(in_array((int) $id, $this->groupPressingIds(), true), 403);

        return Pressing::findOrFail((int) $id);
    }

    /** @return list<Pressing> */
    private function targets(array $ids, string $sourceId): array
    {
        return collect($ids)->reject(fn ($id) => (string) $id === $sourceId)
            ->map(fn ($id) => $this->groupPressing((string) $id))->values()->all();
    }

    public function syncCatalog(SyncCatalog $sync): void
    {
        $source = $this->groupPressing($this->catalogSource);
        $targets = $this->targets($this->catalogTargets, $this->catalogSource);
        if ($targets === []) {
            $this->addError('catalogTargets', 'Choisissez au moins un pressing à mettre à jour.');

            return;
        }

        $created = $updated = $skipped = $services = 0;
        foreach ($targets as $target) {
            $stats = $sync->handle($source, $target);
            $created += $stats['created'];
            $updated += $stats['updated'];
            $skipped += $stats['skipped'];
            $services += $stats['services'];
        }

        $this->catalogTargets = [];
        $this->toast("Services et tarifs harmonisés : {$services} service(s) et {$created} pièce(s) ajoutés, {$updated} pièce(s) mises à jour".($skipped ? ", {$skipped} non ajoutée(s) (limite de formule)" : '').'.');
    }

    public function syncSettings(): void
    {
        $source = $this->groupPressing($this->settingsSource);
        $targets = $this->targets($this->settingsTargets, $this->settingsSource);
        $fields = array_values(array_intersect($this->settingsFields, array_keys(self::SETTINGS)));

        if ($targets === [] || $fields === []) {
            $this->addError('settingsTargets', 'Choisissez au moins un pressing et un paramètre.');

            return;
        }

        foreach ($targets as $target) {
            $data = [];
            foreach ($fields as $field) {
                if ($field === 'logo') {
                    // Copie du fichier : chaque pressing garde le sien (un
                    // changement de logo supprime l'ancien fichier).
                    if ($target->logo_path) {
                        Storage::disk('public')->delete($target->logo_path);
                    }
                    $data['logo_path'] = null;
                    if ($source->logo_path && Storage::disk('public')->exists($source->logo_path)) {
                        $copy = 'pressings/'.Str::uuid().'.'.pathinfo($source->logo_path, PATHINFO_EXTENSION);
                        Storage::disk('public')->copy($source->logo_path, $copy);
                        $data['logo_path'] = $copy;
                    }
                } else {
                    $data[$field] = $source->{$field};
                }
            }
            $target->update($data);

            ActivityLog::record($target->id, ActivityAction::SettingsSynced,
                'Paramètres repris de '.$source->name.' : '.implode(', ', array_map(fn ($f) => mb_strtolower(self::SETTINGS[$f]), $fields)),
                null, ['fields' => $fields, 'source_pressing_id' => $source->id]);
        }

        $this->settingsTargets = [];
        $this->toast(count($targets).' pressing(s) mis à jour.');
    }

    #[Layout('layouts.dashboard', ['active' => 'group-harmonize', 'title' => 'Harmoniser le groupe'])]
    public function render(SyncCatalog $sync)
    {
        $pressings = $this->groupPressings();
        $source = $pressings->firstWhere('id', (int) $this->catalogSource);

        // Aperçu : ce que l'harmonisation changerait chez chaque pressing.
        $preview = $source
            ? $pressings->reject(fn ($p) => $p->id === $source->id)
                ->mapWithKeys(fn ($p) => [$p->id => $sync->handle($source, $p, dryRun: true)])
            : collect();

        return view('livewire.group.harmonize', [
            'pressings' => $pressings,
            'catalogSourcePressing' => $source,
            'sourceServicesCount' => $source?->services()->where('is_active', true)->count() ?? 0,
            'preview' => $preview,
            'settings' => self::SETTINGS,
        ]);
    }
}
