<?php

namespace App\Actions\Offline;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\CreateOrderAction;
use App\Enums\Feature;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\SubscriptionStatus;
use App\Models\CashClosing;
use App\Models\Customer;
use App\Models\OfflineOperation;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\User;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Applique les opérations faites sur le comptoir hors connexion, dans
 * l'ordre où elles ont été faites : nouveau client, nouvelle commande,
 * encaissement, changement de statut (dont la remise au client).
 *
 * Chaque opération porte un identifiant unique créé sur le PC : déjà reçue,
 * elle n'est pas rejouée (son résultat est renvoyé). Une commande, un client
 * ou un paiement déjà promis au comptoir n'est jamais perdu : seul un refus
 * net (commande annulée entre-temps, rôle insuffisant…) est renvoyé au PC,
 * qui l'affiche pour qu'on le reprenne à la main.
 *
 * Résultat par opération : ['uuid', 'ok', 'message'?, 'retry'?, + identifiants
 * créés]. `retry` : panne passagère, l'opération est à renvoyer telle quelle.
 */
class ApplyOfflineOperations
{
    /** Une opération ne peut pas dater de plus loin (horloge du PC déréglée). */
    private const MAX_AGE_DAYS = 7;

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return list<array<string, mixed>>
     */
    public function handle(Pressing $pressing, User $user, array $operations): array
    {
        $results = [];

        foreach ($operations as $operation) {
            $uuid = (string) ($operation['uuid'] ?? '');
            if (! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
                $results[] = ['uuid' => $uuid, 'ok' => false, 'message' => 'Opération illisible.'];

                continue;
            }

            $done = OfflineOperation::where('uuid', $uuid)->where('pressing_id', $pressing->id)->first();
            if ($done !== null) {
                $results[] = $done->result;

                continue;
            }

            $type = (string) ($operation['type'] ?? '');
            $at = $this->performedAt($operation['at'] ?? null);

            try {
                // Réservée puis appliquée dans une même transaction : deux envois
                // simultanés de la même opération n'en appliquent qu'un, et un
                // échec à l'enregistrement annule aussi la commande créée.
                $result = DB::transaction(function () use ($pressing, $user, $operation, $uuid, $type, $at) {
                    $reserved = DB::table('offline_operations')->insertOrIgnore([
                        'uuid' => $uuid, 'pressing_id' => $pressing->id, 'user_id' => $user->id, 'type' => mb_substr($type, 0, 20),
                        'result' => json_encode(['uuid' => $uuid, 'ok' => false, 'retry' => true]), 'performed_at' => $at,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    if ($reserved === 0) {
                        return OfflineOperation::where('uuid', $uuid)->where('pressing_id', $pressing->id)->first()?->result
                            ?? ['uuid' => $uuid, 'ok' => false, 'retry' => true, 'message' => 'Envoi en cours ailleurs, nouvel essai plus tard.'];
                    }

                    $this->ensureAllowed($pressing, $at);

                    $result = ['uuid' => $uuid, 'ok' => true, ...match ($type) {
                        'client' => $this->createCustomer($pressing, $user, $operation),
                        'commande' => $this->createOrder($pressing, $user, $operation, $at),
                        'paiement' => $this->recordPayment($pressing, $user, $operation, $at),
                        'statut' => $this->changeStatus($pressing, $operation, $at),
                        default => throw new InvalidArgumentException('Opération inconnue.'),
                    }];
                    OfflineOperation::where('uuid', $uuid)->update(['result' => json_encode($result)]);

                    return $result;
                });
            } catch (ValidationException|RuntimeException|InvalidArgumentException $e) {
                // Refus net : noté (hors de la transaction annulée) pour ne pas être rejoué.
                $message = $e instanceof ValidationException ? (collect($e->errors())->flatten()->first() ?? 'Données invalides.') : $e->getMessage();
                $result = ['uuid' => $uuid, 'ok' => false, 'message' => $message];
                OfflineOperation::firstOrCreate(['uuid' => $uuid], [
                    'pressing_id' => $pressing->id, 'user_id' => $user->id, 'type' => mb_substr($type, 0, 20), 'result' => $result, 'performed_at' => $at,
                ]);
            } catch (Throwable $e) {
                // Panne imprévue (base indisponible…) : rien n'est noté, le PC
                // garde l'opération et la renverra.
                report($e);
                $result = ['uuid' => $uuid, 'ok' => false, 'retry' => true, 'message' => 'Envoi interrompu, nouvel essai plus tard.'];
            }

            $results[] = $result;
        }

        return $results;
    }

    private function performedAt(mixed $value): CarbonImmutable
    {
        try {
            $at = is_string($value) ? CarbonImmutable::parse($value)->setTimezone(config('app.timezone')) : null;
        } catch (Throwable) {
            $at = null;
        }

        $now = CarbonImmutable::now();

        return ($at === null || $at->isAfter($now) || $at->lt($now->subDays(self::MAX_AGE_DAYS))) ? $now : $at;
    }

    /**
     * Le pressing avait-il le droit de travailler hors connexion au moment de
     * l'opération ? (Formule ou option « Mode hors connexion », abonnement
     * valide ce jour-là ; un essai en cours ouvre tout.)
     */
    private function ensureAllowed(Pressing $pressing, CarbonImmutable $at): void
    {
        $subscription = $pressing->subscription;
        if ($subscription === null) {
            return;
        }

        $inTrial = $subscription->status === SubscriptionStatus::Trialing
            && ($subscription->trial_ends_at === null || $subscription->trial_ends_at->gte($at));
        if ($inTrial) {
            return;
        }

        if ($subscription->status === SubscriptionStatus::Trialing) {
            throw new RuntimeException('Opération faite après la fin de l\'essai gratuit : non enregistrée.');
        }

        if (! $subscription->status->allowsOrderCreation()
            && ($subscription->current_period_ends_at === null || $subscription->current_period_ends_at->lt($at))) {
            throw new RuntimeException('Abonnement expiré au moment de l\'opération : non enregistrée.');
        }

        if (! $subscription->plan->includes(Feature::Offline) && ! $subscription->hasAddon(Feature::Offline)) {
            throw new RuntimeException('Le mode hors connexion n\'est pas inclus dans la formule de ce pressing.');
        }
    }

    /** Date d'un encaissement : jamais dans une journée de caisse déjà clôturée. */
    private function paymentDate(Pressing $pressing, CarbonImmutable $at): CarbonImmutable
    {
        $closed = CashClosing::where('pressing_id', $pressing->id)->whereDate('business_date', $at->toDateString())->exists();

        return $closed ? CarbonImmutable::now() : $at;
    }

    /** @return array{customer_id: int, merged: bool} */
    private function createCustomer(Pressing $pressing, User $user, array $operation): array
    {
        if (! $user->is_super_admin && ! $user->may(Permission::OrdersCreate, $pressing)) {
            throw new RuntimeException('Votre rôle ne permet pas de créer un client.');
        }

        $phone = PhoneNumber::normalize((string) ($operation['phone'] ?? ''));
        Validator::make(
            ['phone' => $phone, 'first_name' => $operation['first_name'] ?? null, 'last_name' => $operation['last_name'] ?? null],
            ['phone' => ['required', new IvorianPhone], 'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100']],
        )->validate();

        // Même numéro déjà connu (créé ailleurs entre-temps) : une seule fiche.
        $customer = Customer::where('phone', $phone)->first();
        $merged = $customer !== null;

        DB::transaction(function () use (&$customer, $pressing, $phone, $operation) {
            $customer ??= Customer::create([
                'phone' => $phone,
                'first_name' => trim((string) $operation['first_name']),
                'last_name' => trim((string) $operation['last_name']),
            ]);
            $pressing->customers()->syncWithoutDetaching([$customer->id => ['joined_at' => now()]]);
        });

        return ['customer_id' => $customer->id, 'merged' => $merged];
    }

    /** @return array{order_id: int, order_number: string, renumbered: bool} */
    private function createOrder(Pressing $pressing, User $user, array $operation, CarbonImmutable $at): array
    {
        $customer = $this->customerOf($pressing, $operation);

        $items = array_map(fn (array $item) => [
            'service_id' => isset($item['service_id']) ? (int) $item['service_id'] : null,
            'service_variant_id' => isset($item['service_variant_id']) ? (int) $item['service_variant_id'] : null,
            'prestation_id' => isset($item['prestation_id']) ? (int) $item['prestation_id'] : null,
            'prestation_name' => isset($item['prestation_name']) ? mb_substr((string) $item['prestation_name'], 0, 100) : null,
            'name' => mb_substr(trim((string) ($item['name'] ?? '')), 0, 200),
            'color' => isset($item['color']) ? mb_substr((string) $item['color'], 0, 40) : null,
            'unit_price_fcfa' => max(0, (int) ($item['unit_price_fcfa'] ?? 0)),
            'quantity' => max(1, min(999, (int) ($item['quantity'] ?? 1))),
        ], array_values(array_filter((array) ($operation['items'] ?? []), 'is_array')));

        if (collect($items)->contains(fn (array $item) => $item['name'] === '')) {
            throw new InvalidArgumentException('Une pièce de la commande n\'a pas de nom.');
        }

        // Le numéro imprimé sur le reçu, sauf s'il a été pris entre-temps.
        $wanted = (string) ($operation['number'] ?? '');
        $keepNumber = preg_match('/^PL-\d{6}$/', $wanted) === 1 && ! Order::withTrashed()->where('order_number', $wanted)->exists();

        $total = array_sum(array_map(fn (array $item) => $item['unit_price_fcfa'] * $item['quantity'], $items));
        $paid = max(0, min($total, (int) ($operation['paid'] ?? 0)));
        // Acompte seulement si le rôle permet d'encaisser.
        $paymentRefused = $paid > 0 && ! $user->is_super_admin && ! $user->may(Permission::PaymentsRecord, $pressing);
        if ($paymentRefused) {
            $paid = 0;
        }
        $expected = $operation['expected_at'] ?? null;
        $validExpected = is_string($expected) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $expected, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);

        $order = (new CreateOrderAction)->handle(
            pressing: $pressing,
            customer: $customer,
            items: $items,
            expectedAt: $validExpected ? $expected : null,
            notes: filled($operation['notes'] ?? null) ? mb_substr((string) $operation['notes'], 0, 1000) : null,
            amountPaidFcfa: $paid,
            paymentMethod: PaymentMethod::tryFrom((string) ($operation['method'] ?? '')) ?? PaymentMethod::Cash,
            offline: true,
            orderNumber: $keepNumber ? $wanted : null,
            droppedOffAt: $at,
        );

        $order->forceFill(['created_at' => $at])->saveQuietly();
        $order->statusHistory()->oldest('id')->first()?->forceFill(['created_at' => $at])->save();
        // Acompte d'une journée déjà clôturée : compté dans la caisse d'aujourd'hui.
        $paidAt = $this->paymentDate($pressing, $at);
        if ($paid > 0 && ! $paidAt->equalTo($at)) {
            $order->payments()->update(['paid_at' => $paidAt]);
        }

        return ['order_id' => $order->id, 'order_number' => $order->order_number, 'renumbered' => ! $keepNumber && $wanted !== '']
            + ($paymentRefused ? ['message' => 'Acompte non enregistré : votre rôle ne permet pas d\'encaisser.'] : []);
    }

    /** @return array{payment_id: int, amount: int} */
    private function recordPayment(Pressing $pressing, User $user, array $operation, CarbonImmutable $at): array
    {
        if (! $user->is_super_admin && ! $user->may(Permission::PaymentsRecord, $pressing)) {
            throw new RuntimeException('Votre rôle ne permet pas d\'encaisser.');
        }

        $order = $this->orderOf($pressing, $operation);
        $method = PaymentMethod::tryFrom((string) ($operation['method'] ?? '')) ?? PaymentMethod::Cash;

        $at = $this->paymentDate($pressing, $at);

        return DB::transaction(function () use ($order, $operation, $method, $user, $at) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === OrderStatus::Annulee) {
                throw new RuntimeException("La commande {$locked->order_number} a été annulée entre-temps : encaissement non enregistré.");
            }

            // Jamais plus que le reste dû (réglé ailleurs entre-temps).
            $amount = min(max(0, (int) ($operation['amount'] ?? 0)), (int) $locked->balance_due_fcfa);
            if ($amount === 0) {
                throw new RuntimeException("La commande {$locked->order_number} était déjà réglée : encaissement non enregistré.");
            }

            $payment = $locked->recordPayment($amount, $method, $user->id, 'Encaissé hors connexion', $at);

            return ['payment_id' => $payment->id, 'amount' => $amount];
        });
    }

    /** @return array{order_id: int, status: string} */
    private function changeStatus(Pressing $pressing, array $operation, CarbonImmutable $at): array
    {
        $order = $this->orderOf($pressing, $operation);
        $target = OrderStatus::tryFrom((string) ($operation['status'] ?? ''));

        if (! in_array($target, [OrderStatus::Traitement, OrderStatus::Prete, OrderStatus::Recuperee], true)) {
            throw new InvalidArgumentException('Statut inconnu.');
        }

        // Déjà fait ailleurs entre-temps (même statut) : rien à refaire.
        if ($order->status === $target) {
            return ['order_id' => $order->id, 'status' => $target->value];
        }

        $method = PaymentMethod::tryFrom((string) ($operation['method'] ?? ''));

        (new ChangeOrderStatusAction)->handle(
            $order,
            $target,
            settleBalanceWith: $target === OrderStatus::Recuperee ? ($method ?? PaymentMethod::Cash) : null,
            waiveStorageFee: (bool) ($operation['waive_storage_fee'] ?? false),
            offline: true,
            performedAt: $target === OrderStatus::Recuperee ? $this->paymentDate($pressing, $at) : $at,
        );

        return ['order_id' => $order->id, 'status' => $target->value];
    }

    /** Client déjà au fichier (id) ou créé hors connexion (uuid de l'opération « client »). */
    private function customerOf(Pressing $pressing, array $operation): Customer
    {
        $id = $operation['customer_id'] ?? null;

        if ($id === null && is_string($operation['customer_uuid'] ?? null)) {
            $created = OfflineOperation::where('uuid', $operation['customer_uuid'])->where('pressing_id', $pressing->id)->first();
            $id = ($created?->result['ok'] ?? false) ? ($created->result['customer_id'] ?? null) : null;
            if ($id === null) {
                throw new RuntimeException('Le nouveau client de cette commande n\'a pas pu être enregistré.');
            }
        }

        $customer = $pressing->customers()->where('customers.id', (int) $id)->first();
        if ($customer === null) {
            throw new RuntimeException('Client introuvable dans ce pressing.');
        }

        return $customer;
    }

    /** Commande déjà au serveur (id) ou créée hors connexion (uuid de l'opération « commande »). */
    private function orderOf(Pressing $pressing, array $operation): Order
    {
        $id = $operation['order_id'] ?? null;

        if ($id === null && is_string($operation['order_uuid'] ?? null)) {
            $created = OfflineOperation::where('uuid', $operation['order_uuid'])->where('pressing_id', $pressing->id)->first();
            $id = ($created?->result['ok'] ?? false) ? ($created->result['order_id'] ?? null) : null;
            if ($id === null) {
                throw new RuntimeException('La commande concernée n\'a pas pu être enregistrée.');
            }
        }

        $order = $pressing->orders()->whereKey((int) $id)->first();
        if ($order === null) {
            throw new RuntimeException('Commande introuvable dans ce pressing.');
        }

        return $order;
    }
}
