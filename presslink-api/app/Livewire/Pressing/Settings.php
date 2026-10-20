<?php

namespace App\Livewire\Pressing;

use App\Enums\ActivityAction;
use App\Enums\Permission;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ActivityLog;
use App\Models\Pressing;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Paramètres du pressing : horaires d'ouverture, affichage des prix,
 * conditions et règlement (visibles par les clients et sur les reçus) et
 * droit de magasinage des commandes prêtes non retirées. Les informations
 * affichées aux clients (logo, nom, adresse…) sont dans Pressing\Profile.
 */
class Settings extends Component
{
    use ShowsToasts;

    private const DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    public Pressing $pressing;

    /** @var array<string, array{closed: bool, open: string, close: string}> */
    public array $openingHours = [];

    public bool $showPricingToCustomers = false;

    public string $terms = '';

    // Droit de magasinage
    public bool $storageFeeEnabled = false;

    public string $storageFeeGraceDays = '7';

    public string $storageFeeAmount = '';

    public string $storageFeeUnit = 'order';

    public string $storageFeePeriod = 'day';

    public string $storageFeeCap = '';

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->may(Permission::SettingsManage, $pressing), 403);

        $this->pressing = $pressing;
        $this->openingHours = $this->normalizeOpeningHours($pressing->opening_hours);
        $this->showPricingToCustomers = $pressing->show_pricing_to_customers;
        $this->terms = $pressing->terms ?? '';

        $fee = $pressing->storage_fee ?? [];
        $this->storageFeeEnabled = (bool) ($fee['enabled'] ?? false);
        $this->storageFeeGraceDays = (string) ($fee['grace_days'] ?? 7);
        $this->storageFeeAmount = isset($fee['amount']) ? (string) $fee['amount'] : '';
        $this->storageFeeUnit = $fee['unit'] ?? 'order';
        $this->storageFeePeriod = $fee['period'] ?? 'day';
        $this->storageFeeCap = isset($fee['cap']) ? (string) $fee['cap'] : '';
    }

    public function save(): void
    {
        $this->validate([
            'openingHours.*.closed' => ['boolean'],
            'openingHours.*.open' => ['nullable', 'date_format:H:i'],
            'openingHours.*.close' => ['nullable', 'date_format:H:i'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'storageFeeGraceDays' => ['required_if:storageFeeEnabled,true', 'nullable', 'integer', 'min:0', 'max:365'],
            'storageFeeAmount' => ['required_if:storageFeeEnabled,true', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'storageFeeUnit' => [Rule::in(['order', 'piece'])],
            'storageFeePeriod' => [Rule::in(array_keys(Pressing::STORAGE_FEE_PERIODS))],
            'storageFeeCap' => ['nullable', 'integer', 'min:1', 'max:100000000'],
        ], [
            'storageFeeAmount.required_if' => 'Indiquez le montant du droit de magasinage.',
            'storageFeeAmount.min' => 'Le montant doit être d\'au moins 1 F.',
            'storageFeeGraceDays.required_if' => 'Indiquez le délai avant facturation.',
        ]);

        $this->pressing->update([
            'opening_hours' => $this->openingHours,
            'show_pricing_to_customers' => $this->showPricingToCustomers,
            'terms' => trim($this->terms) ?: null,
            'storage_fee' => [
                'enabled' => $this->storageFeeEnabled,
                'grace_days' => (int) ($this->storageFeeGraceDays ?: 0),
                'amount' => (int) ($this->storageFeeAmount ?: 0),
                'unit' => $this->storageFeeUnit,
                'period' => $this->storageFeePeriod,
                'cap' => $this->storageFeeCap !== '' ? (int) $this->storageFeeCap : null,
            ],
        ]);

        $labels = ['opening_hours' => 'horaires', 'show_pricing_to_customers' => 'affichage des prix',
            'terms' => 'conditions et règlement', 'storage_fee' => 'droit de magasinage'];
        $changed = array_intersect_key($labels, $this->pressing->getChanges());
        if ($changed !== []) {
            ActivityLog::record($this->pressing->id, ActivityAction::SettingsChanged, 'Paramètres modifiés : '.implode(', ', $changed),
                null, ['fields' => array_keys($changed)]);
        }

        $this->toast('Paramètres enregistrés.');
    }

    /**
     * @param  array<string, mixed>|null  $hours
     * @return array<string, array{closed: bool, open: string, close: string}>
     */
    private function normalizeOpeningHours(?array $hours): array
    {
        $normalized = [];

        foreach (self::DAYS as $day) {
            $normalized[$day] = [
                'closed' => $hours[$day]['closed'] ?? false,
                'open' => $hours[$day]['open'] ?? '08:00',
                'close' => $hours[$day]['close'] ?? '18:00',
            ];
        }

        return $normalized;
    }

    #[Layout('layouts.dashboard', ['active' => 'settings', 'title' => 'Paramètres'])]
    public function render()
    {
        return view('livewire.pressing.settings', [
            'days' => self::DAYS,
            'storageFeePreview' => $this->previewClause(),
        ]);
    }

    /** La règle de magasinage telle que la lira le client, d'après le formulaire en cours. */
    private function previewClause(): ?string
    {
        $preview = $this->pressing->replicate();
        $preview->storage_fee = [
            'enabled' => $this->storageFeeEnabled,
            'grace_days' => (int) ($this->storageFeeGraceDays ?: 0),
            'amount' => (int) ($this->storageFeeAmount ?: 0),
            'unit' => $this->storageFeeUnit,
            'period' => $this->storageFeePeriod,
            'cap' => $this->storageFeeCap !== '' ? (int) $this->storageFeeCap : null,
        ];

        return $preview->storageFeeClause();
    }
}
