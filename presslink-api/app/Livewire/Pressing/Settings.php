<?php

namespace App\Livewire\Pressing;

use App\Enums\ActivityAction;
use App\Enums\Permission;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Rules\IvorianPhone;
use App\Support\ImageOptimizer;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Paramètres du pressing, sur une seule page : informations et logo vus par
 * les clients, contact du pressing (fiche du pressing et bas de chaque
 * commande dans l'appli), horaires, affichage des prix, conditions et
 * règlement, droit de magasinage. Le profil de la personne connectée est
 * dans Account\Settings (« Profil »).
 */
class Settings extends Component
{
    use ShowsToasts, WithFileUploads;

    private const DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    public Pressing $pressing;

    // Informations vues par les clients
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $city = '';

    public string $description = '';

    public $logo = null;

    // Contact du pressing pour ses clients
    public string $supportPhone = '';

    public string $supportWhatsapp = '';

    public string $supportEmail = '';

    public string $supportHours = '';

    /** @var array<string, array{closed: bool, open: string, close: string}> */
    public array $openingHours = [];

    /**
     * Horaires tels qu'affichés à l'ouverture de la page, quand le pressing n'en
     * avait pas (ouvert à toute heure) : laissés tels quels, ils ne sont pas
     * enregistrés (sinon le pressing se fermerait hors 08:00-18:00 sans le savoir).
     *
     * @var array<string, array{closed: bool, open: string, close: string}>|null
     */
    #[Locked]
    public ?array $untouchedDefaultHours = null;

    public bool $showPricingToCustomers = false;

    public string $terms = '';

    // Droit de magasinage
    public bool $storageFeeEnabled = false;

    /** flat (montant unique), per_piece (selon la pièce) ou tiers (paliers). */
    public string $storageFeeMode = 'flat';

    /** @var array<int|string, string> montant par pièce du catalogue (id => montant) */
    public array $storageFeePieceAmounts = [];

    public string $storageFeeDefaultAmount = '';

    /** @var list<array{periods: string, amount: string}> paliers ; le dernier s'applique ensuite sans limite */
    public array $storageFeeTiers = [];

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
        $this->name = $pressing->name;
        $this->phone = $pressing->phone;
        $this->email = $pressing->email ?? '';
        $this->address = $pressing->address ?? '';
        $this->city = $pressing->city ?? '';
        $this->description = $pressing->description ?? '';
        $contact = $pressing->support_contact ?? [];
        $this->supportPhone = $contact['phone'] ?? '';
        $this->supportWhatsapp = $contact['whatsapp'] ?? '';
        $this->supportEmail = $contact['email'] ?? '';
        $this->supportHours = $contact['hours'] ?? '';
        $this->openingHours = $this->normalizeOpeningHours($pressing->opening_hours);
        $this->untouchedDefaultHours = empty($pressing->opening_hours) ? $this->openingHours : null;
        $this->showPricingToCustomers = $pressing->show_pricing_to_customers;
        $this->terms = $pressing->terms ?? '';

        $fee = $pressing->storage_fee ?? [];
        $this->storageFeeEnabled = (bool) ($fee['enabled'] ?? false);
        $this->storageFeeGraceDays = (string) ($fee['grace_days'] ?? 7);
        $this->storageFeeAmount = (int) ($fee['amount'] ?? 0) > 0 ? (string) $fee['amount'] : '';
        $this->storageFeeUnit = $fee['unit'] ?? 'order';
        $this->storageFeePeriod = $fee['period'] ?? 'day';
        $this->storageFeeCap = isset($fee['cap']) ? (string) $fee['cap'] : '';
        $this->storageFeeMode = in_array($fee['mode'] ?? 'flat', Pressing::STORAGE_FEE_MODES, true) ? ($fee['mode'] ?? 'flat') : 'flat';
        $this->storageFeePieceAmounts = collect($fee['piece_amounts'] ?? [])->map(fn ($amount) => (string) $amount)->all();
        $this->storageFeeDefaultAmount = isset($fee['default_amount']) && (int) $fee['default_amount'] > 0 ? (string) $fee['default_amount'] : '';
        $this->storageFeeTiers = collect($fee['tiers'] ?? [])->map(fn ($tier) => ['periods' => (string) ($tier['periods'] ?? ''), 'amount' => (string) ($tier['amount'] ?? '')])->values()->all()
            ?: [['periods' => '1', 'amount' => ''], ['periods' => '', 'amount' => '']];
    }

    public function addTier(): void
    {
        // Le nouveau palier se place avant le dernier (celui « ensuite »).
        array_splice($this->storageFeeTiers, max(0, count($this->storageFeeTiers) - 1), 0, [['periods' => '1', 'amount' => '']]);
    }

    public function removeTier(int $index): void
    {
        if (count($this->storageFeeTiers) > 1 && isset($this->storageFeeTiers[$index])) {
            unset($this->storageFeeTiers[$index]);
            $this->storageFeeTiers = array_values($this->storageFeeTiers);
        }
    }

    /** @return array<string, mixed> réglage du droit de magasinage, tel que saisi */
    private function storageFeeSettings(): array
    {
        $lastTier = count($this->storageFeeTiers) - 1;

        return [
            'enabled' => $this->storageFeeEnabled,
            'mode' => $this->storageFeeMode,
            'grace_days' => (int) ($this->storageFeeGraceDays ?: 0),
            'period' => $this->storageFeePeriod,
            'unit' => $this->storageFeeUnit,
            'amount' => (int) ($this->storageFeeAmount ?: 0),
            'piece_amounts' => collect($this->storageFeePieceAmounts)->map(fn ($amount) => (int) $amount)->filter()->all(),
            'default_amount' => (int) ($this->storageFeeDefaultAmount ?: 0),
            'tiers' => collect($this->storageFeeTiers)->map(fn ($tier, $i) => [
                'periods' => $i === $lastTier ? null : max(1, (int) $tier['periods']),
                'amount' => (int) ($tier['amount'] ?: 0),
            ])->values()->all(),
            'cap' => $this->storageFeeCap !== '' ? (int) $this->storageFeeCap : null,
        ];
    }

    public function save(): void
    {
        $this->phone = PhoneNumber::normalize($this->phone);
        $this->supportPhone = filled($this->supportPhone) ? PhoneNumber::normalize($this->supportPhone) : '';
        $this->supportWhatsapp = filled($this->supportWhatsapp) ? PhoneNumber::normalize($this->supportWhatsapp) : '';

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', new IvorianPhone(mobileOnly: false)],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width='.ImageOptimizer::MAX_DIMENSION.',max_height='.ImageOptimizer::MAX_DIMENSION],
            'supportPhone' => ['nullable', 'string', new IvorianPhone(mobileOnly: false)],
            'supportWhatsapp' => ['nullable', 'string', new IvorianPhone],
            'supportEmail' => ['nullable', 'email', 'max:255'],
            'supportHours' => ['nullable', 'string', 'max:150'],
            'openingHours.*.closed' => ['boolean'],
            'openingHours.*.open' => ['nullable', 'date_format:H:i'],
            'openingHours.*.close' => ['nullable', 'date_format:H:i'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'storageFeeMode' => [Rule::in(Pressing::STORAGE_FEE_MODES)],
            'storageFeeGraceDays' => ['required_if:storageFeeEnabled,true', 'nullable', 'integer', 'min:0', 'max:365'],
            'storageFeeAmount' => $this->storageFeeEnabled && $this->storageFeeMode === 'flat'
                ? ['required', 'integer', 'min:1', 'max:1000000']
                : ['nullable', 'integer', 'min:0', 'max:1000000'],
            'storageFeePieceAmounts.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'storageFeeDefaultAmount' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'storageFeeTiers.*.periods' => ['nullable', 'integer', 'min:1', 'max:365'],
            'storageFeeTiers.*.amount' => [$this->storageFeeEnabled && $this->storageFeeMode === 'tiers' ? 'required' : 'nullable', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'storageFeeUnit' => [Rule::in(['order', 'piece'])],
            'storageFeePeriod' => [Rule::in(array_keys(Pressing::STORAGE_FEE_PERIODS))],
            'storageFeeCap' => ['nullable', 'integer', 'min:1', 'max:100000000'],
        ], [
            'storageFeeAmount.required' => 'Indiquez le montant du droit de magasinage.',
            'storageFeeTiers.*.amount.required' => 'Indiquez le montant de chaque palier.',
            'logo.dimensions' => 'Ce logo est trop grand ('.ImageOptimizer::MAX_DIMENSION.' × '.ImageOptimizer::MAX_DIMENSION.' pixels maximum).',
            'storageFeeAmount.min' => 'Le montant doit être d\'au moins 1 F.',
            'storageFeeGraceDays.required_if' => 'Indiquez le délai avant facturation.',
        ]);

        foreach ($this->openingHours as $day => $hours) {
            if (! ($hours['closed'] ?? false) && filled($hours['open'] ?? null) && filled($hours['close'] ?? null) && $hours['close'] <= $hours['open']) {
                $this->addError("openingHours.{$day}.close", 'L\'heure de fermeture doit être après l\'heure d\'ouverture.');

                return;
            }
        }

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'city' => $this->city ?: null,
            'description' => $this->description ?: null,
            'support_contact' => array_filter([
                'phone' => $this->supportPhone ?: null,
                'whatsapp' => $this->supportWhatsapp ?: null,
                'email' => trim($this->supportEmail) ?: null,
                'hours' => trim($this->supportHours) ?: null,
            ]) ?: null,
            'opening_hours' => $this->untouchedDefaultHours !== null && $this->openingHours == $this->untouchedDefaultHours
                ? $this->pressing->opening_hours
                : $this->openingHours,
            'show_pricing_to_customers' => $this->showPricingToCustomers,
            'terms' => trim($this->terms) ?: null,
            'storage_fee' => $this->storageFeeSettings(),
        ];

        if ($this->logo) {
            if ($this->pressing->logo_path) {
                Storage::disk('public')->delete($this->pressing->logo_path);
            }

            $data['logo_path'] = ImageOptimizer::store($this->logo, 'pressings');
        }

        $this->pressing->update($data);
        $this->logo = null;

        $labels = ['name' => 'nom', 'phone' => 'téléphone', 'email' => 'e-mail', 'address' => 'adresse', 'city' => 'ville',
            'description' => 'description', 'logo_path' => 'logo', 'support_contact' => 'contact du pressing',
            'opening_hours' => 'horaires', 'show_pricing_to_customers' => 'affichage des prix',
            'terms' => 'conditions et règlement', 'storage_fee' => 'droit de magasinage'];
        $changed = array_intersect_key($labels, $this->pressing->getChanges());
        if ($changed !== []) {
            ActivityLog::record($this->pressing->id, ActivityAction::SettingsChanged, 'Paramètres modifiés : '.implode(', ', $changed),
                null, ['fields' => array_keys($changed)]);
        }

        $this->toast('Paramètres enregistrés.');
    }

    public function removeLogo(): void
    {
        if ($this->pressing->logo_path) {
            Storage::disk('public')->delete($this->pressing->logo_path);
            $this->pressing->update(['logo_path' => null]);
            ActivityLog::record($this->pressing->id, ActivityAction::SettingsChanged, 'Logo retiré');
            $this->toast('Logo retiré.');
        }
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
            'pieces' => $this->pressing->services()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'icon']),
        ]);
    }

    /** La règle de magasinage telle que la lira le client, d'après le formulaire en cours. */
    private function previewClause(): ?string
    {
        $preview = clone $this->pressing;
        $preview->storage_fee = $this->storageFeeSettings();

        return $preview->storageFeeClause();
    }
}
