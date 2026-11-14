<?php

namespace App\Actions\Deliveries;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Enums\ActivityAction;
use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Mail\DeliveryRequestAlert;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\User;
use App\Notifications\DeliveryMissedNotification;
use App\Notifications\DeliveryRescheduledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Livraison à domicile d'une commande prête : le client la demande dans
 * l'appli sur un créneau du pressing (pas d'acceptation : le pressing a
 * publié ses créneaux, il peut reprogrammer), le coursier la remet et
 * encaisse le reste à payer à la porte, frais de livraison compris. Comme
 * au comptoir, on ne remet qu'une commande soldée. Un refus de règle lève
 * une RuntimeException au message affichable.
 */
class OrderDeliveries
{
    public function __construct(private ChangeOrderStatusAction $changeStatus) {}

    /**
     * Ce que le client peut demander pour cette commande, ou la raison pour
     * laquelle la livraison n'est pas proposée.
     *
     * @return array{available: bool, reason: ?string, fee_fcfa: int, communes: list<string>, days: list<array{date: string, label: string, slots: list<array{from: string, to: string}>}>}
     */
    public function offer(Order $order): array
    {
        $pressing = $order->pressing;
        $services = $pressing->deliveryServices();
        $none = ['available' => false, 'reason' => null, 'fee_fcfa' => 0, 'communes' => [], 'days' => []];

        if ($services === null || ! $services['delivery']) {
            return [...$none, 'reason' => "{$pressing->name} ne propose pas la livraison."];
        }
        if ($order->status !== OrderStatus::Prete) {
            return [...$none, 'reason' => 'La livraison se demande quand la commande est prête.'];
        }
        if ($order->scheduledDelivery()->exists()) {
            return [...$none, 'reason' => 'Une livraison est déjà prévue pour cette commande.'];
        }

        return [
            'available' => true,
            'reason' => null,
            'fee_fcfa' => $pressing->homeServiceFee($services['delivery_fee_fcfa'], (int) $order->total_fcfa),
            'communes' => $services['communes'],
            'days' => $pressing->deliveryAvailability(),
        ];
    }

    /**
     * @param  array{delivery_date: string, slot_from: string, slot_to: string, note?: ?string}  $data
     */
    public function request(Customer $customer, Order $order, CustomerAddress $address, array $data): OrderDelivery
    {
        if ($order->customer_id !== $customer->id || $address->customer_id !== $customer->id) {
            throw new RuntimeException('Commande ou adresse introuvable.');
        }

        $offer = $this->offer($order);
        if (! $offer['available']) {
            throw new RuntimeException((string) $offer['reason']);
        }

        if (! in_array($address->commune, $offer['communes'], true)) {
            throw new RuntimeException("{$order->pressing->name} ne livre pas à {$address->commune}.");
        }

        $day = collect($offer['days'])->firstWhere('date', $data['delivery_date']);
        $slotIsOffered = $day !== null && collect($day['slots'])
            ->contains(fn (array $slot) => $slot['from'] === $data['slot_from'] && $slot['to'] === $data['slot_to']);
        if (! $slotIsOffered) {
            throw new RuntimeException('Ce créneau n\'est plus disponible. Choisissez-en un autre.');
        }

        return DB::transaction(function () use ($customer, $order, $address, $data, $offer) {
            // Verrou sur la commande : deux envois simultanés ne prévoient qu'une livraison.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== OrderStatus::Prete || $locked->scheduledDelivery()->exists()) {
                throw new RuntimeException('Une livraison est déjà prévue pour cette commande.');
            }

            $delivery = OrderDelivery::create([
                'pressing_id' => $locked->pressing_id,
                'order_id' => $locked->id,
                'customer_id' => $customer->id,
                'status' => OrderDeliveryStatus::Scheduled,
                'address_label' => $address->label,
                'commune' => $address->commune,
                'neighborhood' => $address->neighborhood,
                'landmark' => $address->landmark,
                'delivery_date' => $data['delivery_date'],
                'slot_from' => $data['slot_from'],
                'slot_to' => $data['slot_to'],
                'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
                'delivery_fee_fcfa' => $offer['fee_fcfa'],
            ]);

            ActivityLog::record($locked->pressing_id, ActivityAction::DeliveryRequested,
                "Livraison de {$locked->order_number} demandée par {$customer->fullName()} pour {$delivery->scheduleLabel()} ({$delivery->place()})", $locked, ['order_delivery_id' => $delivery->id]);

            $this->alertAdmins($delivery);

            return $delivery;
        });
    }

    public function cancelByCustomer(OrderDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $locked = $this->lockScheduled($delivery);
            $locked->update(['status' => OrderDeliveryStatus::Cancelled, 'closed_at' => now()]);

            ActivityLog::record($locked->pressing_id, ActivityAction::DeliveryCancelled,
                "Livraison de {$locked->order->order_number} ({$locked->scheduleLabel()}) annulée par le client", $locked->order, ['order_delivery_id' => $locked->id]);
        });

        $delivery->refresh();
        $this->alertAdmins($delivery, cancelled: true);
    }

    public function reschedule(OrderDelivery $delivery, User $user, string $date, string $slotFrom, string $slotTo): void
    {
        $this->ensureRunsRounds($delivery, $user);

        DB::transaction(function () use ($delivery, $user, $date, $slotFrom, $slotTo) {
            $locked = $this->lockScheduled($delivery);
            if ($date === $locked->delivery_date->toDateString() && $slotFrom === $locked->slot_from && $slotTo === $locked->slot_to) {
                return;
            }

            $locked->update([
                'delivery_date' => $date,
                'slot_from' => $slotFrom,
                'slot_to' => $slotTo,
                'rescheduled' => true,
                'handled_by' => $user->id,
            ]);

            ActivityLog::record($locked->pressing_id, ActivityAction::DeliveryRescheduled,
                "Livraison de {$locked->order->order_number} reprogrammée : {$locked->scheduleLabel()}", $locked->order, ['order_delivery_id' => $locked->id]);

            $locked->customer->notify(new DeliveryRescheduledNotification($locked->order, $locked));
        });

        $delivery->refresh();
    }

    /**
     * Commande remise chez le client : les frais de livraison s'ajoutent, le
     * reste à payer est encaissé avec `$method` (obligatoire s'il en reste),
     * la commande passe « Récupérée » et la livraison « Livrée ».
     */
    public function deliver(OrderDelivery $delivery, User $user, ?PaymentMethod $method): void
    {
        $this->ensureRunsRounds($delivery, $user);

        DB::transaction(function () use ($delivery, $user, $method) {
            $locked = $this->lockScheduled($delivery);
            $order = $locked->order;
            if ($order->status !== OrderStatus::Prete) {
                throw new RuntimeException("La commande {$order->order_number} n'est pas prête ({$order->status->label()}).");
            }

            if ($method === null && $locked->amountToCollect() > 0) {
                throw new RuntimeException('Choisissez comment le client a payé.');
            }

            // Livraison marquée avant la remise : la commande n'annule pas sa propre livraison.
            $locked->update(['status' => OrderDeliveryStatus::Delivered, 'handled_by' => $user->id, 'delivered_at' => now(), 'closed_at' => now()]);

            if ($locked->delivery_fee_fcfa > 0) {
                $order->items()->create(['service_id' => null, 'name' => Order::DELIVERY_FEE_LINE, 'unit_price_fcfa' => $locked->delivery_fee_fcfa, 'quantity' => 1]);
            }

            $this->changeStatus->handle($order->fresh(), OrderStatus::Recuperee, settleBalanceWith: $method, atDoor: true);
        });

        $delivery->refresh();
    }

    /** Le coursier est passé, le client n'était pas là : la commande reste prête, le client choisit un autre créneau. */
    public function markMissed(OrderDelivery $delivery, User $user): void
    {
        $this->ensureRunsRounds($delivery, $user);

        DB::transaction(function () use ($delivery, $user) {
            $locked = $this->lockScheduled($delivery);
            $locked->update(['status' => OrderDeliveryStatus::Missed, 'handled_by' => $user->id, 'closed_at' => now()]);

            ActivityLog::record($locked->pressing_id, ActivityAction::DeliveryMissed,
                "Livraison de {$locked->order->order_number} manquée chez {$locked->customer->fullName()} : client absent", $locked->order, ['order_delivery_id' => $locked->id]);

            $locked->customer->notify(new DeliveryMissedNotification($locked->order, $locked));
        });

        $delivery->refresh();
    }

    /** Commande remise au comptoir alors qu'une livraison était prévue : la livraison tombe. */
    public function cancelForCounterHandover(Order $order): void
    {
        $order->deliveries()->where('status', OrderDeliveryStatus::Scheduled->value)->get()
            ->each(fn (OrderDelivery $delivery) => $delivery->update([
                'status' => OrderDeliveryStatus::Cancelled,
                'cancel_reason' => 'Commande récupérée au pressing',
                'closed_at' => now(),
            ]));
    }

    private function ensureRunsRounds(OrderDelivery $delivery, User $user): void
    {
        if (! $user->is_super_admin && ! $user->may(Permission::DeliveriesRun, $delivery->pressing)) {
            throw new RuntimeException('Votre rôle ne permet pas de faire la tournée.');
        }
    }

    private function lockScheduled(OrderDelivery $delivery): OrderDelivery
    {
        $locked = OrderDelivery::with(['order.pressing', 'customer', 'pressing'])->lockForUpdate()->findOrFail($delivery->id);
        if (! $locked->isScheduled()) {
            throw new RuntimeException('Cette livraison n\'est plus prévue ('.mb_strtolower($locked->status->label()).').');
        }

        return $locked;
    }

    /**
     * E-mail aux administrateurs actifs, envoyé après la réponse et sans
     * file d'attente : un échec SMTP est journalisé, jamais bloquant.
     */
    private function alertAdmins(OrderDelivery $delivery, bool $cancelled = false): void
    {
        $pressing = $delivery->pressing;
        if ($pressing->isSandbox()) {
            return;
        }

        $emails = $pressing->admins()->wherePivot('is_active', true)->whereNotNull('email')->pluck('email')->unique()->all();
        if ($emails === []) {
            return;
        }

        defer(function () use ($delivery, $emails, $cancelled): void {
            try {
                Mail::to($emails)->send(new DeliveryRequestAlert($delivery, $cancelled));
            } catch (Throwable $e) {
                Log::warning('Alerte e-mail de livraison non envoyée', ['order_delivery_id' => $delivery->id, 'error' => $e->getMessage()]);
            }
        });
    }
}
