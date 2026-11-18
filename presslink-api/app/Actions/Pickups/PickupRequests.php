<?php

namespace App\Actions\Pickups;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\ActivityAction;
use App\Enums\Permission;
use App\Enums\PickupRequestStatus;
use App\Enums\PressingStatus;
use App\Mail\PickupRequestAlert;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\PickupRequestDismissal;
use App\Models\PickupRequestPhoto;
use App\Models\Pressing;
use App\Models\User;
use App\Notifications\PickupAcceptedNotification;
use App\Notifications\PickupExpiredNotification;
use App\Notifications\PickupMissedNotification;
use App\Notifications\PickupRefusedNotification;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Demandes de ramassage. Le client n'a pas besoin d'être déjà client d'un
 * pressing : il voit les pressings qui passent dans sa commune et en choisit
 * un, ou laisse la demande ouverte à la zone, où le premier pressing qui
 * l'accepte la prend (les autres peuvent la masquer). Le pressing accepte
 * (en gardant ou en changeant le créneau) ou refuse ; le client devient
 * alors l'un de ses clients. Sur place, le coursier marque le linge ramassé
 * (la demande devient un dépôt rapide) ou le client absent. Les règles
 * métier sont ici ; un refus de règle lève une RuntimeException au message
 * affichable.
 */
class PickupRequests
{
    public function __construct(private CreateOrderAction $createOrder) {}

    /**
     * Ce que le client peut choisir pour une adresse : les pressings qui
     * passent dans sa commune (ses pressings d'abord) avec leurs frais et
     * leurs créneaux, et la demande ouverte à la zone, sur l'ensemble de
     * leurs créneaux.
     *
     * @return array{commune: string, pressings: list<array<string, mixed>>, zone: array{days: list<array<string, mixed>>, fee_min_fcfa: int, fee_max_fcfa: int}|null}
     */
    public function options(Customer $customer, CustomerAddress $address): array
    {
        $mine = $customer->pressings()->pluck('pressings.id')->all();

        $pressings = Pressing::pickupProvidersIn($address->commune, $customer)
            ->map(fn (Pressing $pressing) => [
                'is_demo' => $pressing->is_demo,
                'id' => $pressing->id,
                'name' => $pressing->name,
                'logo_url' => $pressing->logo_url,
                'address' => $pressing->address,
                'pickup_fee_fcfa' => $pressing->pickup_fee_fcfa,
                'is_mine' => in_array($pressing->id, $mine, true),
                'days' => $pressing->pickupAvailability(),
            ])
            ->reject(fn (array $option) => $option['days'] === [])
            ->sortBy([['is_mine', 'desc'], ['name', 'asc']])
            ->values();

        // La demande ouverte à la zone ne part qu'aux vrais pressings.
        $zonePressings = $pressings->reject(fn (array $option) => $option['is_demo']);
        $pressings = $pressings->map(fn (array $option) => Arr::except($option, 'is_demo'));

        $zone = null;
        if ($zonePressings->isNotEmpty()) {
            $days = $zonePressings->flatMap(fn (array $option) => $option['days'])
                ->groupBy('date')
                ->map(fn ($sameDay) => [
                    'date' => $sameDay->first()['date'],
                    'label' => $sameDay->first()['label'],
                    'slots' => $sameDay->flatMap(fn (array $day) => $day['slots'])
                        ->unique(fn (array $slot) => "{$slot['from']}-{$slot['to']}")
                        ->sortBy(fn (array $slot) => "{$slot['from']}-{$slot['to']}")
                        ->values()->all(),
                ])
                ->sortKeys()->values()->all();

            $zone = [
                'days' => $days,
                'fee_min_fcfa' => (int) $zonePressings->min('pickup_fee_fcfa'),
                'fee_max_fcfa' => (int) $zonePressings->max('pickup_fee_fcfa'),
            ];
        }

        return ['commune' => $address->commune, 'pressings' => $pressings->all(), 'zone' => $zone];
    }

    /**
     * Envoie la demande au pressing choisi, ou à toute la zone si $pressing est null.
     *
     * @param  array{pickup_date: string, slot_from: string, slot_to: string, items_estimate?: ?int, note?: ?string}  $data
     */
    public function create(Customer $customer, ?Pressing $pressing, CustomerAddress $address, array $data): PickupRequest
    {
        if ($address->customer_id !== $customer->id) {
            throw new RuntimeException('Adresse introuvable.');
        }

        if ($pressing !== null) {
            $services = $pressing->deliveryServices();
            if ($services === null || ! $services['pickup'] || $pressing->status !== PressingStatus::Active) {
                throw new RuntimeException("{$pressing->name} ne propose pas le ramassage.");
            }
            $followsDemo = $pressing->is_demo && ! $pressing->isSandbox()
                && $customer->pressings()->whereKey($pressing->id)->exists();
            if (! $pressing->picksUpIn($address->commune) || ($pressing->is_demo && ! $followsDemo)) {
                throw new RuntimeException("{$pressing->name} ne passe pas à {$address->commune}.");
            }
            $candidates = collect([$pressing]);
        } else {
            $candidates = Pressing::pickupProvidersIn($address->commune);
            if ($candidates->isEmpty()) {
                throw new RuntimeException("Aucun pressing ne passe encore à {$address->commune}.");
            }
        }

        $slotIsOffered = $candidates->contains(function (Pressing $candidate) use ($data) {
            $day = collect($candidate->pickupAvailability())->firstWhere('date', $data['pickup_date']);

            return $day !== null && collect($day['slots'])
                ->contains(fn (array $slot) => $slot['from'] === $data['slot_from'] && $slot['to'] === $data['slot_to']);
        });
        if (! $slotIsOffered) {
            throw new RuntimeException('Ce créneau n\'est plus disponible. Choisissez-en un autre.');
        }

        $request = DB::transaction(function () use ($customer, $pressing, $address, $data) {
            // Verrou sur le client : deux envois simultanés ne contournent pas les limites.
            Customer::whereKey($customer->id)->lockForUpdate()->first();

            $open = PickupRequest::where('customer_id', $customer->id)->open();
            if ($pressing !== null && (clone $open)->where('pressing_id', $pressing->id)->exists()) {
                throw new RuntimeException("Vous avez déjà une demande en cours chez {$pressing->name}.");
            }
            if ($pressing === null && (clone $open)->whereNull('pressing_id')->exists()) {
                throw new RuntimeException('Vous avez déjà une demande en attente d\'un pressing de votre zone.');
            }
            if ($open->count() >= PickupRequest::MAX_OPEN_PER_CUSTOMER) {
                throw new RuntimeException('Vous avez déjà '.PickupRequest::MAX_OPEN_PER_CUSTOMER.' demandes de ramassage en cours.');
            }

            return PickupRequest::create([
                'pressing_id' => $pressing?->id,
                'customer_id' => $customer->id,
                'status' => PickupRequestStatus::Pending,
                'address_label' => $address->label,
                'commune' => $address->commune,
                'neighborhood' => $address->neighborhood,
                'landmark' => $address->landmark,
                'pickup_date' => $data['pickup_date'],
                'slot_from' => $data['slot_from'],
                'slot_to' => $data['slot_to'],
                'items_estimate' => $data['items_estimate'] ?? null,
                'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
                // Ouverte à la zone : les frais sont ceux du pressing qui l'accepte.
                'pickup_fee_fcfa' => $pressing?->pickup_fee_fcfa ?? 0,
            ]);
        });

        if ($pressing !== null) {
            ActivityLog::record($pressing->id, ActivityAction::PickupRequested,
                "Demande de ramassage de {$customer->fullName()} pour {$request->scheduleLabel()} ({$request->place()})", null, ['pickup_request_id' => $request->id]);
        }

        foreach ($candidates as $candidate) {
            $this->alertAdmins($request, recipient: $candidate);
        }

        return $request;
    }

    public function addPhoto(PickupRequest $request, UploadedFile $file): PickupRequestPhoto
    {
        if ($request->status !== PickupRequestStatus::Pending) {
            throw new RuntimeException('Les photos s\'ajoutent tant que la demande attend la réponse du pressing.');
        }

        if ($request->photos()->count() >= PickupRequestPhoto::MAX_PER_REQUEST) {
            throw new RuntimeException('Cette demande a déjà '.PickupRequestPhoto::MAX_PER_REQUEST.' photos, le maximum.');
        }

        $full = ImageOptimizer::toWebp((string) $file->getRealPath(), 1600);
        $thumb = ImageOptimizer::toWebp((string) $file->getRealPath(), 400);
        if ($full === null || $thumb === null) {
            throw new RuntimeException('Cette image n\'a pas pu être lue. Reprenez la photo.');
        }

        $base = PickupRequestPhoto::directoryFor($request->pressing_id ?? PickupRequestPhoto::ZONE_DIRECTORY, $request->id).'/'.Str::random(40);
        Storage::disk(PickupRequestPhoto::DISK)->put("{$base}.webp", $full);
        Storage::disk(PickupRequestPhoto::DISK)->put("{$base}-mini.webp", $thumb);

        return $request->photos()->create([
            'path' => "{$base}.webp",
            'thumb_path' => "{$base}-mini.webp",
            'size_bytes' => strlen($full) + strlen($thumb),
        ]);
    }

    public function cancelByCustomer(PickupRequest $request): void
    {
        $wasAccepted = $request->status === PickupRequestStatus::Accepted;
        $this->close($request, PickupRequestStatus::Cancelled);

        if ($request->pressing_id === null) {
            return;
        }

        ActivityLog::record($request->pressing_id, ActivityAction::PickupCancelled,
            "Ramassage de {$request->customer->fullName()} ({$request->scheduleLabel()}) annulé par le client", null, ['pickup_request_id' => $request->id]);

        // Le pressing avait prévu de passer : il est prévenu.
        if ($wasAccepted) {
            $this->alertAdmins($request, cancelled: true);
        }
    }

    /**
     * Le pressing accepte la demande, sur le créneau du client ou sur un autre
     * (« reprogrammer »). Une demande ouverte à la zone revient au premier
     * pressing qui l'accepte. Sert aussi à reprogrammer un ramassage déjà prévu.
     */
    public function accept(PickupRequest $request, User $user, Pressing $pressing, ?string $date = null, ?string $slotFrom = null, ?string $slotTo = null): void
    {
        DB::transaction(fn () => $this->acceptLocked($this->lockOpen($request), $user, $pressing, $date, $slotFrom, $slotTo));
        $request->refresh();
    }

    private function acceptLocked(PickupRequest $locked, User $user, Pressing $pressing, ?string $date, ?string $slotFrom, ?string $slotTo): void
    {
        $takenFromZone = $locked->isOpenToZone();
        if ($takenFromZone) {
            if ($pressing->is_demo || ! $pressing->picksUpIn($locked->commune)) {
                throw new RuntimeException("Votre pressing ne passe pas à {$locked->commune}.");
            }
            $locked->forceFill(['pressing_id' => $pressing->id, 'pickup_fee_fcfa' => $pressing->pickup_fee_fcfa]);
            $locked->setRelation('pressing', $pressing);
        } elseif ($locked->pressing_id !== $pressing->id) {
            throw new RuntimeException('Un autre pressing a déjà pris cette demande.');
        }

        $moved = ($date !== null && $date !== $locked->pickup_date->toDateString())
            || ($slotFrom !== null && $slotFrom !== $locked->slot_from)
            || ($slotTo !== null && $slotTo !== $locked->slot_to);

        if ($locked->status === PickupRequestStatus::Accepted && ! $moved) {
            return;
        }

        // Le client devient l'un des clients du pressing (ses commandes, « Mes pressings »).
        if (! $locked->customer->pressings()->whereKey($pressing->id)->exists()) {
            $locked->customer->pressings()->attach($pressing->id, ['joined_at' => now()]);
        }

        $locked->update([
            'status' => PickupRequestStatus::Accepted,
            'pickup_date' => $date ?? $locked->pickup_date,
            'slot_from' => $slotFrom ?? $locked->slot_from,
            'slot_to' => $slotTo ?? $locked->slot_to,
            'rescheduled' => $locked->rescheduled || $moved,
            'responded_by' => $user->id,
            'responded_at' => now(),
        ]);

        $what = match (true) {
            $takenFromZone => 'Ramassage pris dans la zone',
            $moved => 'Ramassage reprogrammé',
            default => 'Ramassage accepté',
        };
        ActivityLog::record($locked->pressing_id, ActivityAction::PickupAccepted,
            "{$what} pour {$locked->customer->fullName()} : {$locked->scheduleLabel()}", null, ['pickup_request_id' => $locked->id]);

        $locked->customer->notify(new PickupAcceptedNotification($locked));
    }

    public function refuse(PickupRequest $request, User $user, string $reason): void
    {
        DB::transaction(fn () => $this->refuseLocked($this->lockOpen($request), $user, $reason));
        $request->refresh();
    }

    private function refuseLocked(PickupRequest $locked, User $user, string $reason): void
    {
        if ($locked->isOpenToZone()) {
            throw new RuntimeException('Cette demande est ouverte à toute la zone : masquez-la avec « Pas pour nous ».');
        }

        if ($locked->status !== PickupRequestStatus::Pending) {
            throw new RuntimeException('Ce ramassage est déjà prévu : reprogrammez-le, ou appelez le client.');
        }

        $locked->update(['refusal_reason' => mb_substr(trim($reason), 0, 255), 'responded_by' => $user->id, 'responded_at' => now()]);
        $this->close($locked, PickupRequestStatus::Refused);

        ActivityLog::record($locked->pressing_id, ActivityAction::PickupRefused,
            "Ramassage refusé pour {$locked->customer->fullName()} : {$locked->refusal_reason}", null, ['pickup_request_id' => $locked->id]);

        $locked->customer->notify(new PickupRefusedNotification($locked));
    }

    /** « Pas pour nous » : le pressing masque chez lui une demande ouverte à sa zone. */
    public function dismiss(PickupRequest $request, Pressing $pressing): void
    {
        if (! $request->isOpenToZone() || $request->status !== PickupRequestStatus::Pending) {
            throw new RuntimeException('Cette demande n\'est plus ouverte à la zone.');
        }

        PickupRequestDismissal::firstOrCreate(['pickup_request_id' => $request->id, 'pressing_id' => $pressing->id]);
    }

    /**
     * Linge ramassé chez le client : le dépôt rapide est créé avec les pièces
     * comptées sur place ; le détail et les prix se saisissent à l'atelier.
     */
    public function markPickedUp(PickupRequest $request, User $user, int $countedPieces, ?string $note = null): Order
    {
        $this->ensureRunsRounds($request, $user);

        return DB::transaction(function () use ($request, $user, $countedPieces, $note) {
            $locked = $this->lockOpen($request);
            if ($locked->status !== PickupRequestStatus::Accepted) {
                throw new RuntimeException('Acceptez d\'abord la demande avant de ramasser le linge.');
            }

            $notes = collect([filled($note) ? trim($note) : null, $locked->note ? "Message du client : {$locked->note}" : null])
                ->filter()->implode("\n") ?: null;
            $order = $this->createOrder->pickedUpDeposit($locked, $countedPieces, $notes !== null ? mb_substr($notes, 0, 1000) : null);

            $locked->update([
                'status' => PickupRequestStatus::PickedUp,
                'order_id' => $order->id,
                'picked_up_at' => now(),
                'picked_up_by' => $user->id,
                'closed_at' => now(),
            ]);

            ActivityLog::record($locked->pressing_id, ActivityAction::PickupCollected,
                "Linge de {$locked->customer->fullName()} ramassé ({$locked->place()}) : dépôt {$order->order_number}", $order, ['pickup_request_id' => $locked->id]);

            $request->refresh();

            return $order;
        });
    }

    /** Le coursier est passé, le client n'était pas là : la demande se termine, le client est prévenu. */
    public function markMissed(PickupRequest $request, User $user): void
    {
        $this->ensureRunsRounds($request, $user);

        DB::transaction(function () use ($request, $user) {
            $locked = $this->lockOpen($request);
            if ($locked->status !== PickupRequestStatus::Accepted) {
                throw new RuntimeException('Ce ramassage n\'était pas prévu.');
            }

            $locked->update(['picked_up_by' => $user->id]);
            $this->close($locked, PickupRequestStatus::Missed);

            ActivityLog::record($locked->pressing_id, ActivityAction::PickupMissed,
                "Ramassage manqué chez {$locked->customer->fullName()} ({$locked->place()}) : client absent", null, ['pickup_request_id' => $locked->id]);

            $locked->customer->notify(new PickupMissedNotification($locked));
        });

        $request->refresh();
    }

    private function ensureRunsRounds(PickupRequest $request, User $user): void
    {
        if ($request->pressing === null || (! $user->is_super_admin && ! $user->may(Permission::DeliveriesRun, $request->pressing))) {
            throw new RuntimeException('Votre rôle ne permet pas de faire la tournée.');
        }
    }

    /** Demandes restées sans réponse après la fin de leur créneau. */
    public function expireOverdue(): int
    {
        $expired = 0;
        PickupRequest::where('status', PickupRequestStatus::Pending->value)
            ->whereDate('pickup_date', '<=', now()->toDateString())
            ->with(['pressing', 'customer'])
            ->each(function (PickupRequest $request) use (&$expired): void {
                if ($request->slotEndsAt()->isFuture()) {
                    return;
                }

                $this->close($request, PickupRequestStatus::Expired);
                $request->customer->notify(new PickupExpiredNotification($request));
                $expired++;
            });

        return $expired;
    }

    /** Photos des demandes terminées depuis plus de 30 jours (refusées, annulées, expirées). */
    public function purgeOldPhotos(): int
    {
        $photos = PickupRequestPhoto::whereHas('pickupRequest', fn ($query) => $query
            ->whereNotIn('status', PickupRequestStatus::openValues())
            ->where('closed_at', '<', now()->subDays(PickupRequest::PHOTO_RETENTION_DAYS)))
            ->get();

        $photos->each->delete();

        return $photos->count();
    }

    private function lockOpen(PickupRequest $request): PickupRequest
    {
        $locked = PickupRequest::with(['customer', 'pressing'])->lockForUpdate()->findOrFail($request->id);
        if (! $locked->status->isOpen()) {
            throw new RuntimeException('Cette demande n\'est plus en cours ('.mb_strtolower($locked->status->label()).').');
        }

        return $locked;
    }

    private function close(PickupRequest $request, PickupRequestStatus $status): void
    {
        if (! $request->status->isOpen()) {
            throw new RuntimeException('Cette demande n\'est plus en cours ('.mb_strtolower($request->status->label()).').');
        }

        $request->update(['status' => $status, 'closed_at' => now()]);
    }

    /**
     * E-mail aux administrateurs actifs, envoyé après la réponse et sans
     * file d'attente : un échec SMTP est journalisé, jamais bloquant.
     */
    private function alertAdmins(PickupRequest $request, bool $cancelled = false, ?Pressing $recipient = null): void
    {
        $pressing = $recipient ?? $request->pressing;
        if ($pressing === null || $pressing->isSandbox()) {
            return;
        }

        $emails = $pressing->admins()->wherePivot('is_active', true)->whereNotNull('email')->pluck('email')->unique()->all();
        if ($emails === []) {
            return;
        }

        defer(function () use ($request, $emails, $cancelled, $pressing): void {
            try {
                Mail::to($emails)->send(new PickupRequestAlert($request, $cancelled, $pressing));
            } catch (Throwable $e) {
                Log::warning('Alerte e-mail de ramassage non envoyée', ['pickup_request_id' => $request->id, 'error' => $e->getMessage()]);
            }
        });
    }
}
