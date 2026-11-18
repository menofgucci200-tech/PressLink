<?php

namespace App\Livewire\Round;

use App\Actions\Deliveries\OrderDeliveries;
use App\Actions\Pickups\PickupRequests;
use App\Enums\Feature;
use App\Enums\OrderDeliveryStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\PickupRequestStatus;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\OrderDelivery;
use App\Models\PickupRequest;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Tournée du coursier, sur téléphone : les ramassages acceptés et les
 * livraisons prévues du jour, dans l'ordre des créneaux (les passages en
 * retard remontent sur aujourd'hui). Sur place : linge ramassé (le dépôt
 * rapide est créé), commande livrée et payée, ou client absent.
 */
class Index extends Component
{
    use ShowsToasts;

    /** Jours affichés dans le sélecteur (aujourd'hui compris). */
    private const DAYS = 7;

    /** Jours proposés pour reprogrammer une livraison. */
    private const RESCHEDULE_DAYS = 14;

    public Pressing $pressing;

    #[Url(as: 'jour')]
    public string $day = '';

    /** Filtre de qui organise la tournée : '' tous, 'none' sans coursier, ou l'id d'un coursier. */
    #[Url(as: 'coursier')]
    public string $courierFilter = '';

    /** Arrêt en cours de traitement : « pickup-12 » ou « delivery-5 ». */
    public ?string $acting = null;

    /** collect, deliver, missed ou reschedule */
    public string $mode = '';

    public string $pieces = '';

    public string $note = '';

    public string $paymentMethod = '';

    public string $date = '';

    public string $slot = '';

    public function hydrate(): void
    {
        $user = auth()->user();
        abort_unless($user->belongsToPressing($this->pressing) && $this->pressing->hasFeature(Feature::Delivery), 403);
    }

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing !== null && $pressing->hasFeature(Feature::Delivery), 403);

        $this->pressing = $pressing;
        if (! array_key_exists($this->day, $this->dayOptions())) {
            $this->day = today()->toDateString();
        }
    }

    public function updatingDay(): void
    {
        $this->stopActing();
    }

    public function startCollect(int $id): void
    {
        $request = $this->findPickup($id);
        $this->startActing("pickup-{$request->id}", 'collect');
        $this->pieces = $request->items_estimate !== null ? (string) $request->items_estimate : '';
        $this->note = '';
    }

    public function startDeliver(int $id): void
    {
        $this->startActing("delivery-{$this->findDelivery($id)->id}", 'deliver');
        $this->paymentMethod = '';
    }

    public function startMissed(string $type, int $id): void
    {
        $key = $type === 'pickup' ? "pickup-{$this->findPickup($id)->id}" : "delivery-{$this->findDelivery($id)->id}";
        $this->startActing($key, 'missed');
    }

    public function startReschedule(int $id): void
    {
        $delivery = $this->findDelivery($id);
        $this->startActing("delivery-{$delivery->id}", 'reschedule');
        $this->date = $delivery->delivery_date->toDateString();
        $this->slot = "{$delivery->slot_from}-{$delivery->slot_to}";
    }

    /**
     * Confie un passage (« pickup-12 » ou « delivery-5 ») à un coursier de
     * l'équipe, ou à personne ('').
     */
    public function assign(string $key, string $courierId, PickupRequests $pickups, OrderDeliveries $deliveries): void
    {
        abort_unless(auth()->user()->dispatchesRounds($this->pressing), 403);
        [$type, $id] = array_pad(explode('-', $key), 2, null);
        abort_unless(in_array($type, ['pickup', 'delivery'], true) && ctype_digit((string) $id), 404);

        $courier = $courierId === '' ? null : $this->pressing->couriers()->firstWhere('id', (int) $courierId);
        abort_if($courierId !== '' && $courier === null, 404);

        try {
            if ($type === 'pickup') {
                $pickups->assign($this->findPickup((int) $id), auth()->user(), $courier);
            } else {
                $deliveries->assign($this->findDelivery((int) $id), auth()->user(), $courier);
            }
        } catch (RuntimeException $e) {
            $this->failed($e);

            return;
        }

        $this->toast($courier ? "Passage confié à {$courier->name}." : 'Passage confié à aucun coursier : tous les coursiers le voient.');
    }

    public function stopActing(): void
    {
        $this->acting = null;
        $this->mode = '';
        $this->resetErrorBag();
    }

    public function confirmCollect(PickupRequests $pickups): void
    {
        $request = $this->findPickup($this->actingId('pickup'));

        $this->validate([
            'pieces' => ['required', 'integer', 'min:1', 'max:9999'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'pieces.required' => 'Indiquez le nombre de pièces comptées.',
            'pieces.min' => 'Comptez au moins une pièce.',
            'pieces.max' => 'Ce nombre de pièces paraît trop grand : vérifiez la saisie.',
        ], ['pieces' => 'nombre de pièces']);

        try {
            $order = $pickups->markPickedUp($request, auth()->user(), (int) $this->pieces, trim($this->note) ?: null);
        } catch (RuntimeException $e) {
            $this->failed($e);

            return;
        }

        $this->stopActing();
        $this->toast("Linge ramassé : dépôt {$order->order_number} créé. Ajoutez les photos depuis la commande.");
    }

    public function confirmDeliver(OrderDeliveries $deliveries): void
    {
        $delivery = $this->findDelivery($this->actingId('delivery'));
        $toCollect = $delivery->amountToCollect();

        $this->validate([
            'paymentMethod' => [Rule::requiredIf($toCollect > 0), 'nullable', Rule::in(array_keys($this->paymentMethods()))],
        ], ['paymentMethod.required' => 'Choisissez comment le client a payé.']);

        try {
            $deliveries->deliver($delivery, auth()->user(), $toCollect > 0 ? PaymentMethod::from($this->paymentMethod) : null);
        } catch (RuntimeException $e) {
            $this->failed($e);

            return;
        }

        $this->stopActing();
        $this->toast($toCollect > 0
            ? 'Commande livrée : '.number_format($toCollect, 0, ',', ' ').' F encaissés.'
            : 'Commande livrée.');
    }

    public function confirmMissed(PickupRequests $pickups, OrderDeliveries $deliveries): void
    {
        [$type] = explode('-', (string) $this->acting);

        try {
            if ($type === 'pickup') {
                $pickups->markMissed($this->findPickup($this->actingId('pickup')), auth()->user());
            } else {
                $deliveries->markMissed($this->findDelivery($this->actingId('delivery')), auth()->user());
            }
        } catch (RuntimeException $e) {
            $this->failed($e);

            return;
        }

        $this->stopActing();
        $this->toast('Passage manqué noté : le client est prévenu.');
    }

    public function confirmReschedule(OrderDeliveries $deliveries): void
    {
        $delivery = $this->findDelivery($this->actingId('delivery'));

        $this->validate([
            'date' => ['required', Rule::in(array_keys($this->rescheduleDates()))],
            'slot' => ['required', Rule::in(array_keys($this->slotOptions($delivery)))],
        ], [], ['date' => 'jour', 'slot' => 'créneau']);

        [$from, $to] = explode('-', $this->slot);

        try {
            $deliveries->reschedule($delivery, auth()->user(), $this->date, $from, $to);
        } catch (RuntimeException $e) {
            $this->failed($e);

            return;
        }

        $this->stopActing();
        $this->toast('Livraison reprogrammée : le client est prévenu.');
    }

    /** @return array<string, string> date => libellé */
    public function dayOptions(): array
    {
        $options = [];
        for ($offset = 0; $offset < self::DAYS; $offset++) {
            $day = today()->addDays($offset);
            $options[$day->toDateString()] = match ($offset) {
                0 => 'Aujourd\'hui',
                1 => 'Demain',
                default => ucfirst($day->locale('fr')->isoFormat('ddd D MMM')),
            };
        }

        return $options;
    }

    /** @return array<string, string> date => libellé */
    public function rescheduleDates(): array
    {
        $options = [];
        for ($offset = 0; $offset < self::RESCHEDULE_DAYS; $offset++) {
            $day = today()->addDays($offset);
            $options[$day->toDateString()] = match ($offset) {
                0 => 'Aujourd\'hui',
                1 => 'Demain',
                default => ucfirst($day->locale('fr')->isoFormat('dddd D MMMM')),
            }.($this->pressing->opensOn($day) ? '' : ' (fermé)');
        }

        return $options;
    }

    /** @return array<string, string> « 14:00-18:00 » => « 14:00 – 18:00 » */
    public function slotOptions(OrderDelivery $delivery): array
    {
        return collect($this->pressing->delivery_slots ?: Pressing::DEFAULT_DELIVERY_SLOTS)
            ->push(['from' => $delivery->slot_from, 'to' => $delivery->slot_to])
            ->mapWithKeys(fn (array $slot) => ["{$slot['from']}-{$slot['to']}" => "{$slot['from']} – {$slot['to']}"])
            ->sortKeys()->all();
    }

    /** @return array<string, string> moyens de paiement à la porte */
    public function paymentMethods(): array
    {
        return collect(PaymentMethod::cases())
            ->reject(fn (PaymentMethod $method) => $method === PaymentMethod::Card)
            ->mapWithKeys(fn (PaymentMethod $method) => [$method->value => $method->label()])
            ->all();
    }

    private function startActing(string $key, string $mode): void
    {
        abort_unless(auth()->user()->may(Permission::DeliveriesRun, $this->pressing), 403);
        $this->resetErrorBag();
        $this->acting = $key;
        $this->mode = $mode;
    }

    /** Identifiant de l'arrêt en cours, s'il est bien du type attendu. */
    private function actingId(string $type): int
    {
        abort_unless(auth()->user()->may(Permission::DeliveriesRun, $this->pressing), 403);
        [$actingType, $id] = array_pad(explode('-', (string) $this->acting), 2, null);
        abort_unless($actingType === $type && $id !== null, 404);

        return (int) $id;
    }

    private function failed(RuntimeException $e): void
    {
        $this->stopActing();
        $this->toast($e->getMessage(), 'error');
    }

    private function findPickup(int $id): PickupRequest
    {
        return $this->visibleToMe($this->pressing->pickupRequests())->findOrFail($id);
    }

    private function findDelivery(int $id): OrderDelivery
    {
        return $this->visibleToMe($this->pressing->orderDeliveries())->with('order')->findOrFail($id);
    }

    /** Organise la tournée (voit et répartit tout), ou coursier (ses passages et ceux de personne). */
    private function dispatches(): bool
    {
        return auth()->user()->dispatchesRounds($this->pressing);
    }

    /**
     * Passages que l'utilisateur peut traiter : tous pour qui organise la
     * tournée ; les siens et ceux de personne pour un coursier.
     */
    private function visibleToMe(HasMany $query): HasMany
    {
        if (! $this->dispatches()) {
            return $query->forCourier(auth()->user());
        }

        return $query;
    }

    /** Passages visibles, puis filtrés par le choix de coursier de qui organise la tournée. */
    private function filteredByCourier(HasMany $query): HasMany
    {
        if (! $this->dispatches()) {
            return $this->visibleToMe($query);
        }

        return match (true) {
            $this->courierFilter === 'none' => $query->whereNull('courier_id'),
            ctype_digit($this->courierFilter) => $query->where('courier_id', (int) $this->courierFilter),
            default => $query,
        };
    }

    /**
     * Arrêts du jour, dans l'ordre de passage.
     *
     * @return Collection<int, array{type: string, key: string, model: PickupRequest|OrderDelivery, late: bool}>
     */
    private function stops(Carbon $day): Collection
    {
        $onDay = fn ($query, string $column) => $day->isToday()
            ? $query->whereDate($column, '<=', $day->toDateString())
            : $query->whereDate($column, $day->toDateString());

        $pickups = $onDay($this->filteredByCourier($this->pressing->pickupRequests())->where('status', PickupRequestStatus::Accepted->value), 'pickup_date')
            ->with(['customer', 'courier'])->get()
            ->map(fn (PickupRequest $request) => [
                'type' => 'pickup', 'key' => "pickup-{$request->id}", 'model' => $request,
                'late' => $request->slotEndsAt()->isPast(), 'sort' => $request->pickup_date->toDateString().$request->slot_from.'0',
            ]);

        $deliveries = $onDay($this->filteredByCourier($this->pressing->orderDeliveries())->where('status', OrderDeliveryStatus::Scheduled->value), 'delivery_date')
            ->with(['customer', 'courier', 'order.items'])->get()
            ->map(fn (OrderDelivery $delivery) => [
                'type' => 'delivery', 'key' => "delivery-{$delivery->id}", 'model' => $delivery,
                'late' => $delivery->slotEndsAt()->isPast(), 'sort' => $delivery->delivery_date->toDateString().$delivery->slot_from.'1',
            ]);

        return $pickups->concat($deliveries)->sortBy('sort')->values();
    }

    #[Layout('layouts.dashboard', ['active' => 'round', 'title' => 'Tournée'])]
    public function render()
    {
        $day = Carbon::parse($this->day);
        $stops = $this->stops($day);

        $donePickups = $this->pressing->pickupRequests()->where('status', PickupRequestStatus::PickedUp->value)
            ->whereDate('picked_up_at', $day->toDateString())->with(['customer', 'order'])->latest('picked_up_at')->get();
        $doneDeliveries = $this->pressing->orderDeliveries()->where('status', OrderDeliveryStatus::Delivered->value)
            ->whereDate('delivered_at', $day->toDateString())->with(['customer', 'order'])->latest('delivered_at')->get();

        $dispatches = $this->dispatches();

        return view('livewire.round.index', [
            'stops' => $stops,
            'dispatches' => $dispatches,
            'couriers' => $dispatches ? $this->pressing->couriers() : collect(),
            'toCollect' => $stops->where('type', 'delivery')->sum(fn (array $stop) => $stop['model']->amountToCollect()),
            'donePickups' => $donePickups,
            'doneDeliveries' => $doneDeliveries,
            'canRun' => auth()->user()->may(Permission::DeliveriesRun, $this->pressing),
            'launched' => Feature::Delivery->isLaunched(),
        ]);
    }
}
