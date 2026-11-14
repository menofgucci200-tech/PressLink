<?php

namespace App\Livewire\Pressing;

use App\Enums\ActivityAction;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Support\Communes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ramassage et livraison (formule Business et au-dessus, ou en option) :
 * services proposés, communes desservies, frais libres, créneaux et délai
 * habituel. Les clients de ces communes pourront envoyer leur commande
 * depuis l'appli une fois le service lancé.
 */
class Delivery extends Component
{
    use ShowsToasts;

    private const MAX_SLOTS = 6;

    public Pressing $pressing;

    public bool $pickupEnabled = false;

    public bool $deliveryEnabled = false;

    /** @var list<string> */
    public array $communes = [];

    public string $pickupFee = '';

    public string $deliveryFee = '';

    public string $freeFrom = '';

    /**
     * Créneaux proposés au client (« $slots » est réservé par Livewire).
     *
     * @var list<array{from: string, to: string}>
     */
    public array $timeSlots = [];

    public string $turnaroundHours = '';

    /** Revérifié à chaque action : le rôle, l'appartenance ou la formule ont pu changer depuis l'ouverture de la page. */
    public function hydrate(): void
    {
        $user = auth()->user();
        abort_unless($user->belongsToPressing($this->pressing) && $user->may(Permission::SettingsManage, $this->pressing)
            && $this->pressing->hasFeature(Feature::Delivery), 403);
    }

    public function mount()
    {
        $user = auth()->user();
        $pressing = $user->currentPressing();
        abort_unless($pressing && $user->may(Permission::SettingsManage, $pressing), 403);

        if (! $pressing->hasFeature(Feature::Delivery)) {
            // Une fois lancé, le service se prend en option depuis l'abonnement.
            abort_unless(Feature::Delivery->isLaunched() && $user->may(Permission::SubscriptionManage, $pressing), 403);

            return redirect()->route('subscription.show', ['option' => Feature::Delivery->value]);
        }

        $this->pressing = $pressing;
        $this->pickupEnabled = $pressing->pickup_enabled;
        $this->deliveryEnabled = $pressing->delivery_enabled;
        $this->communes = $pressing->serviceAreas()->orderBy('commune')->pluck('commune')->all();
        $this->pickupFee = (string) $pressing->pickup_fee_fcfa;
        $this->deliveryFee = (string) $pressing->delivery_fee_fcfa;
        $this->freeFrom = $pressing->delivery_free_from_fcfa !== null ? (string) $pressing->delivery_free_from_fcfa : '';
        $this->timeSlots = $pressing->delivery_slots ?: Pressing::DEFAULT_DELIVERY_SLOTS;
        $this->turnaroundHours = $pressing->turnaround_hours !== null ? (string) $pressing->turnaround_hours : '';
    }

    public function addSlot(): void
    {
        if (count($this->timeSlots) < self::MAX_SLOTS) {
            $this->timeSlots[] = ['from' => '', 'to' => ''];
        }
    }

    public function removeSlot(int $index): void
    {
        unset($this->timeSlots[$index]);
        $this->timeSlots = array_values($this->timeSlots);
    }

    public function save(): void
    {
        $offersService = $this->pickupEnabled || $this->deliveryEnabled;

        $this->validate([
            'communes' => [Rule::requiredIf($offersService), 'array'],
            'communes.*' => ['string', Rule::in(Communes::all())],
            'pickupFee' => ['required', 'integer', 'min:0', 'max:100000'],
            'deliveryFee' => ['required', 'integer', 'min:0', 'max:100000'],
            'freeFrom' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'timeSlots' => ['required', 'array', 'min:1', 'max:'.self::MAX_SLOTS],
            'timeSlots.*.from' => ['required', 'date_format:H:i'],
            'timeSlots.*.to' => ['required', 'date_format:H:i', 'after:timeSlots.*.from'],
            'turnaroundHours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ], [
            'communes.required' => 'Cochez au moins une commune desservie.',
            'timeSlots.*.to.after' => 'La fin du créneau doit être après le début.',
        ], [
            'pickupFee' => 'frais de ramassage',
            'deliveryFee' => 'frais de livraison',
            'freeFrom' => 'montant de gratuité',
            'timeSlots.*.from' => 'début du créneau',
            'timeSlots.*.to' => 'fin du créneau',
            'turnaroundHours' => 'délai habituel',
        ]);

        $communes = array_values(array_unique($this->communes));
        $slots = collect($this->timeSlots)->map(fn (array $slot) => ['from' => $slot['from'], 'to' => $slot['to']])
            ->sortBy('from')->values()->all();
        $previousCommunes = $this->pressing->serviceAreas()->pluck('commune')->all();

        DB::transaction(function () use ($communes, $slots) {
            $this->pressing->update([
                'pickup_enabled' => $this->pickupEnabled,
                'delivery_enabled' => $this->deliveryEnabled,
                'pickup_fee_fcfa' => (int) $this->pickupFee,
                'delivery_fee_fcfa' => (int) $this->deliveryFee,
                'delivery_free_from_fcfa' => $this->freeFrom === '' ? null : (int) $this->freeFrom,
                'delivery_slots' => $slots,
                'turnaround_hours' => $this->turnaroundHours === '' ? null : (int) $this->turnaroundHours,
            ]);

            $this->pressing->serviceAreas()->whereNotIn('commune', $communes)->delete();
            foreach ($communes as $commune) {
                $this->pressing->serviceAreas()->firstOrCreate(['commune' => $commune]);
            }
        });

        $this->communes = $communes;
        $this->timeSlots = $slots;

        $changedCommunes = array_diff($communes, $previousCommunes) !== [] || array_diff($previousCommunes, $communes) !== [];
        if ($this->pressing->getChanges() !== [] || $changedCommunes) {
            ActivityLog::record($this->pressing->id, ActivityAction::SettingsChanged, 'Ramassage et livraison modifiés',
                null, ['fields' => [...array_keys(array_diff_key($this->pressing->getChanges(), ['updated_at' => true])), ...($changedCommunes ? ['communes'] : [])]]);
        }

        $this->toast('Ramassage et livraison enregistrés.');
    }

    #[Layout('layouts.dashboard', ['active' => 'settings', 'title' => 'Ramassage et livraison'])]
    public function render()
    {
        return view('livewire.pressing.delivery', [
            'communeGroups' => Communes::grouped(),
            'launched' => Feature::Delivery->isLaunched(),
            'maxSlots' => self::MAX_SLOTS,
        ]);
    }
}
