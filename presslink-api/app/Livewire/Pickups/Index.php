<?php

namespace App\Livewire\Pickups;

use App\Actions\Pickups\PickupRequests;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\PickupRequestStatus;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\PickupRequest;
use App\Models\Pressing;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Demandes de ramassage reçues de l'appli client : accepter (sur le créneau
 * du client ou un autre), refuser avec un motif, voir les ramassages prévus.
 */
class Index extends Component
{
    use ShowsToasts, WithPagination;

    /** Jours proposés pour reprogrammer un ramassage. */
    private const RESCHEDULE_DAYS = 14;

    private const TABS = ['pending', 'accepted', 'closed'];

    public Pressing $pressing;

    #[Url(as: 'onglet')]
    public string $tab = 'pending';

    public ?int $actingId = null;

    /** « accept » ou « refuse » */
    public string $mode = '';

    public string $date = '';

    /** « 08:00-12:00 » */
    public string $slot = '';

    public string $reason = '';

    public string $otherReason = '';

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
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'pending';
        }
    }

    public function updatingTab(): void
    {
        $this->resetPage();
        $this->stopActing();
    }

    public function startAccept(int $id): void
    {
        $request = $this->find($id);
        $this->resetErrorBag();
        $this->actingId = $request->id;
        $this->mode = 'accept';
        $this->date = $request->pickup_date->toDateString();
        $this->slot = "{$request->slot_from}-{$request->slot_to}";
    }

    public function startRefuse(int $id): void
    {
        $this->resetErrorBag();
        $this->actingId = $this->find($id)->id;
        $this->mode = 'refuse';
        $this->reason = PickupRequest::REFUSAL_REASONS[0];
        $this->otherReason = '';
    }

    public function stopActing(): void
    {
        $this->actingId = null;
        $this->mode = '';
    }

    public function confirmAccept(PickupRequests $pickups): void
    {
        $this->authorizeHandling();
        $request = $this->find((int) $this->actingId);

        $this->validate([
            'date' => ['required', Rule::in(array_keys($this->dateOptions()))],
            'slot' => ['required', Rule::in(array_keys($this->slotOptions($request)))],
        ], [], ['date' => 'jour', 'slot' => 'créneau']);

        [$from, $to] = explode('-', $this->slot);
        $wasPending = $request->status === PickupRequestStatus::Pending;

        try {
            $pickups->accept($request, auth()->user(), $this->date, $from, $to);
        } catch (RuntimeException $e) {
            $this->stopActing();
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->stopActing();
        $this->toast(($wasPending ? 'Ramassage accepté' : 'Ramassage reprogrammé').' : le client est prévenu.');
    }

    public function confirmRefuse(PickupRequests $pickups): void
    {
        $this->authorizeHandling();
        $request = $this->find((int) $this->actingId);

        $this->validate([
            'reason' => ['required', Rule::in([...PickupRequest::REFUSAL_REASONS, 'other'])],
            'otherReason' => ['required_if:reason,other', 'nullable', 'string', 'max:200'],
        ], ['otherReason.required_if' => 'Indiquez le motif du refus.']);

        try {
            $pickups->refuse($request, auth()->user(), $this->reason === 'other' ? $this->otherReason : $this->reason);
        } catch (RuntimeException $e) {
            $this->stopActing();
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->stopActing();
        $this->toast('Demande refusée : le client est prévenu.');
    }

    /** @return array<string, string> date => libellé */
    public function dateOptions(): array
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

    /** @return array<string, string> « 08:00-12:00 » => « 08:00 – 12:00 » */
    public function slotOptions(PickupRequest $request): array
    {
        $slots = collect($this->pressing->delivery_slots ?: Pressing::DEFAULT_DELIVERY_SLOTS)
            ->push(['from' => $request->slot_from, 'to' => $request->slot_to]);

        return $slots->mapWithKeys(fn (array $slot) => ["{$slot['from']}-{$slot['to']}" => "{$slot['from']} – {$slot['to']}"])
            ->sortKeys()->all();
    }

    private function authorizeHandling(): void
    {
        abort_unless(auth()->user()->may(Permission::OrdersCreate, $this->pressing), 403);
    }

    private function find(int $id): PickupRequest
    {
        return $this->pressing->pickupRequests()->findOrFail($id);
    }

    #[Layout('layouts.dashboard', ['active' => 'pickups', 'title' => 'Ramassages'])]
    public function render()
    {
        $base = fn () => $this->pressing->pickupRequests()->with(['customer', 'photos', 'respondedBy']);

        $requests = match ($this->tab) {
            'accepted' => $base()->where('status', PickupRequestStatus::Accepted->value)->orderBy('pickup_date')->orderBy('slot_from'),
            'closed' => $base()->whereNotIn('status', PickupRequestStatus::openValues())->latest('updated_at'),
            default => $base()->where('status', PickupRequestStatus::Pending->value)->orderBy('pickup_date')->orderBy('slot_from'),
        };

        $counts = $this->pressing->pickupRequests()->open()
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.pickups.index', [
            'requests' => $requests->paginate(20),
            'pendingCount' => (int) ($counts[PickupRequestStatus::Pending->value] ?? 0),
            'acceptedCount' => (int) ($counts[PickupRequestStatus::Accepted->value] ?? 0),
            'canHandle' => auth()->user()->may(Permission::OrdersCreate, $this->pressing),
            'refusalReasons' => PickupRequest::REFUSAL_REASONS,
            'launched' => Feature::Delivery->isLaunched(),
            'pickupEnabled' => $this->pressing->pickup_enabled,
            'today' => Carbon::today(),
        ]);
    }
}
