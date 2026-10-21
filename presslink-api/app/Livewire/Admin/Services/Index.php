<?php

namespace App\Livewire\Admin\Services;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Support\ServiceIcons;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Services (Repassage, Nettoyage, Teinture…) — Super Admin : crée un service
 * et le rattache aux pressings choisis, ou à tous. Chaque pressing fixe
 * ensuite ses prix par pièce depuis sa page Services.
 */
class Index extends Component
{
    use ShowsToasts;

    public bool $showForm = false;

    public string $name = '';

    public string $icon = 'autre';

    public bool $iconChosen = false;

    /** 'all' = tous les pressings, 'some' = ceux cochés. */
    public string $target = 'some';

    /** @var list<string> */
    public array $pressingIds = [];

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function newService(): void
    {
        $this->resetErrorBag();
        $this->reset(['name', 'iconChosen', 'pressingIds']);
        $this->icon = 'autre';
        $this->target = 'some';
        $this->showForm = true;
    }

    /** « Rattacher à d'autres pressings » : formulaire prérempli avec ce service. */
    public function attachExisting(string $name): void
    {
        $this->newService();
        $this->name = $name;
        $this->icon = Prestation::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()?->iconKey() ?? ServiceIcons::guessService($name);
        $this->iconChosen = true;
    }

    public function cancel(): void
    {
        $this->reset(['showForm', 'name', 'iconChosen', 'pressingIds']);
        $this->resetErrorBag();
    }

    public function updatedName(): void
    {
        if (! $this->iconChosen) {
            $this->icon = ServiceIcons::guessService($this->name);
        }
    }

    public function updatedIcon(): void
    {
        $this->iconChosen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $this->name = trim($this->name);

        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['required', 'string'],
            'target' => ['required', 'in:all,some'],
            'pressingIds' => ['array', 'required_if:target,some'],
            'pressingIds.*' => ['integer', 'exists:pressings,id'],
        ], [
            'name.required' => 'Donnez un nom au service (ex. Teinture).',
            'pressingIds.required_if' => 'Choisissez au moins un pressing.',
        ]);
        abort_unless(ServiceIcons::has('service', $this->icon), 422);

        $pressings = $this->target === 'all'
            ? Pressing::all()
            : Pressing::whereIn('id', array_map('intval', $this->pressingIds))->get();

        $created = $reactivated = $already = 0;
        foreach ($pressings as $pressing) {
            $existing = $pressing->prestations()->whereRaw('LOWER(name) = ?', [mb_strtolower($this->name)])->first();

            if ($existing !== null) {
                if (! $existing->is_active) {
                    $existing->update(['is_active' => true]);
                    $reactivated++;
                } else {
                    $already++;
                }

                continue;
            }

            $pressing->prestations()->create([
                'name' => $this->name,
                'icon' => $this->icon,
                'position' => (int) $pressing->prestations()->max('position') + 1,
                'is_active' => true,
            ]);
            $created++;
        }

        $parts = array_filter([
            $created > 0 ? "ajouté à {$created} pressing(s)" : null,
            $reactivated > 0 ? "réactivé dans {$reactivated}" : null,
            $already > 0 ? "déjà présent dans {$already}" : null,
        ]);
        $this->toast("Service « {$this->name} » : ".implode(', ', $parts).'. Chaque pressing fixe ses prix depuis sa page Services.');
        $this->cancel();
    }

    /** @return Collection<int, array{name: string, icon: string, pressings: Collection<int, string>}> */
    private function catalog(): Collection
    {
        return Prestation::query()
            ->where('is_active', true)
            ->with('pressing:id,name')
            ->get()
            ->groupBy(fn (Prestation $prestation) => mb_strtolower(trim($prestation->name)))
            ->map(fn (Collection $group) => [
                'name' => $group->first()->name,
                'icon' => $group->first()->iconKey(),
                'pressings' => $group->pluck('pressing.name')->filter()->sort()->values(),
            ])
            ->when($this->search !== '', fn (Collection $c) => $c->filter(fn (array $s) => str_contains(mb_strtolower($s['name']), mb_strtolower($this->search))))
            ->sortByDesc(fn (array $s) => $s['pressings']->count())
            ->values();
    }

    #[Layout('layouts.admin', ['active' => 'services', 'title' => 'Services'])]
    public function render()
    {
        return view('livewire.admin.services.index', [
            'catalog' => $this->catalog(),
            'pressings' => Pressing::orderBy('name')->get(['id', 'name']),
            'icons' => ServiceIcons::options('service'),
        ]);
    }
}
