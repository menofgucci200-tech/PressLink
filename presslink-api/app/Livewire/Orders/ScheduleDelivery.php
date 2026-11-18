<?php

namespace App\Livewire\Orders;

use App\Actions\Deliveries\OrderDeliveries;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use RuntimeException;

/**
 * « Programmer la livraison » sur la fiche d'une commande prête : le pressing
 * choisit l'adresse (celle du ramassage, une adresse enregistrée du client ou
 * une nouvelle), le jour, le créneau et, s'il veut, le coursier. Le client est
 * prévenu dans l'appli ; la livraison rejoint la tournée.
 */
class ScheduleDelivery extends Component
{
    use ShowsToasts;

    /** Jours proposés (aujourd'hui compris). */
    private const DAYS = 14;

    public Order $order;

    public bool $open = false;

    /** 'pickup', 'saved-{id}' ou 'new' */
    public string $addressChoice = 'new';

    public string $commune = '';

    public string $neighborhood = '';

    public string $landmark = '';

    public string $date = '';

    public string $slot = '';

    public string $courierId = '';

    public string $note = '';

    public function openForm(): void
    {
        abort_unless($this->canSchedule(), 403);

        $choices = $this->addressChoices();
        $this->addressChoice = array_key_first($choices) ?? 'new';
        $this->commune = '';
        $this->neighborhood = '';
        $this->landmark = '';
        $this->date = (string) collect($this->dateOptions())->keys()->first();
        $this->slot = (string) collect($this->slotOptions())->keys()->first();
        $this->courierId = '';
        $this->note = '';
        $this->resetErrorBag();
        $this->open = true;
    }

    public function closeForm(): void
    {
        $this->open = false;
        $this->resetErrorBag();
    }

    public function schedule(OrderDeliveries $deliveries): void
    {
        abort_unless($this->canSchedule(), 403);
        $pressing = $this->order->pressing;
        $couriers = $pressing->couriers();

        $this->validate([
            'addressChoice' => ['required', Rule::in(array_keys($this->addressChoices()))],
            'commune' => [Rule::requiredIf($this->addressChoice === 'new'), 'nullable', Rule::in($this->communes())],
            'neighborhood' => ['nullable', 'string', 'max:80'],
            'landmark' => [Rule::requiredIf($this->addressChoice === 'new'), 'nullable', 'string', 'max:255'],
            'date' => ['required', Rule::in(array_keys($this->dateOptions()))],
            'slot' => ['required', Rule::in(array_keys($this->slotOptions()))],
            'courierId' => ['nullable', Rule::in($couriers->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'commune.required' => 'Choisissez la commune.',
            'landmark.required' => 'Indiquez un repère pour que le coursier trouve le client.',
        ], ['commune' => 'commune', 'landmark' => 'repère', 'date' => 'jour', 'slot' => 'créneau', 'courierId' => 'coursier']);

        [$from, $to] = explode('-', $this->slot);
        $courier = $this->courierId === '' ? null : $couriers->firstWhere('id', (int) $this->courierId);

        try {
            $deliveries->schedule($this->order, auth()->user(), $this->chosenAddress(), [
                'delivery_date' => $this->date,
                'slot_from' => $from,
                'slot_to' => $to,
                'note' => $this->note,
            ], $courier);
        } catch (RuntimeException $e) {
            $this->addError('schedule', $e->getMessage());

            return;
        }

        $this->flashToast('Livraison programmée : le client est prévenu dans l\'appli.');
        $this->redirectRoute('orders.show', $this->order, navigate: true);
    }

    /** @return array{address_label: ?string, commune: string, neighborhood: ?string, landmark: string} */
    private function chosenAddress(): array
    {
        if ($this->addressChoice === 'pickup') {
            $pickup = $this->order->pickupRequest;

            return ['address_label' => $pickup->address_label, 'commune' => $pickup->commune, 'neighborhood' => $pickup->neighborhood, 'landmark' => $pickup->landmark];
        }

        if (str_starts_with($this->addressChoice, 'saved-')) {
            $address = $this->savedAddresses()->firstWhere('id', (int) substr($this->addressChoice, 6));

            return ['address_label' => $address->label, 'commune' => $address->commune, 'neighborhood' => $address->neighborhood, 'landmark' => $address->landmark];
        }

        return ['address_label' => null, 'commune' => $this->commune, 'neighborhood' => $this->neighborhood, 'landmark' => $this->landmark];
    }

    /** Qui organise la tournée, sur une commande prête que le pressing peut livrer. */
    private function canSchedule(): bool
    {
        return auth()->user()->dispatchesRounds($this->order->pressing)
            && app(OrderDeliveries::class)->offer($this->order)['available'];
    }

    /** @return list<string> */
    private function communes(): array
    {
        return $this->order->pressing->deliveryServices()['communes'] ?? [];
    }

    /** @return Collection<int, CustomerAddress> */
    private function savedAddresses()
    {
        return $this->order->customer->addresses()->whereIn('commune', $this->communes())->orderByDesc('is_default')->orderBy('label')->get();
    }

    /** @return array<string, string> choix => libellé, les adresses dans les communes desservies d'abord */
    public function addressChoices(): array
    {
        $choices = [];
        $pickup = $this->order->pickupRequest;
        if ($pickup !== null && in_array($pickup->commune, $this->communes(), true)) {
            $choices['pickup'] = "Adresse du ramassage : {$pickup->place()}, {$pickup->landmark}";
        }
        foreach ($this->savedAddresses() as $address) {
            $choices["saved-{$address->id}"] = "{$address->label} : ".implode(', ', array_filter([$address->commune, $address->neighborhood])).", {$address->landmark}";
        }
        $choices['new'] = 'Autre adresse';

        return $choices;
    }

    /** @return array<string, string> date => libellé */
    public function dateOptions(): array
    {
        $options = [];
        for ($offset = 0; $offset < self::DAYS; $offset++) {
            $day = today()->addDays($offset);
            $options[$day->toDateString()] = match ($offset) {
                0 => 'Aujourd\'hui',
                1 => 'Demain',
                default => ucfirst($day->locale('fr')->isoFormat('dddd D MMMM')),
            }.($this->order->pressing->opensOn($day) ? '' : ' (fermé)');
        }

        return $options;
    }

    /** @return array<string, string> « 14:00-18:00 » => « 14:00 – 18:00 » */
    public function slotOptions(): array
    {
        return collect($this->order->pressing->delivery_slots ?: Pressing::DEFAULT_DELIVERY_SLOTS)
            ->mapWithKeys(fn (array $slot) => ["{$slot['from']}-{$slot['to']}" => "{$slot['from']} – {$slot['to']}"])
            ->sortKeys()->all();
    }

    public function render()
    {
        $offer = app(OrderDeliveries::class)->offer($this->order);
        $canSchedule = $offer['available'] && auth()->user()->dispatchesRounds($this->order->pressing);

        return view('livewire.orders.schedule-delivery', [
            'canSchedule' => $canSchedule,
            'fee' => $offer['fee_fcfa'],
            'communes' => $offer['communes'],
            'couriers' => $canSchedule && $this->open ? $this->order->pressing->couriers() : collect(),
            'toCollect' => (int) $this->order->balance_due_fcfa + $offer['fee_fcfa'],
        ]);
    }
}
