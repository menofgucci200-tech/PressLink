<?php

namespace App\Actions\Pickups;

use App\Enums\ActivityAction;
use App\Enums\PickupRequestStatus;
use App\Mail\PickupRequestAlert;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\PickupRequest;
use App\Models\PickupRequestPhoto;
use App\Models\Pressing;
use App\Models\User;
use App\Notifications\PickupAcceptedNotification;
use App\Notifications\PickupExpiredNotification;
use App\Notifications\PickupRefusedNotification;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Demandes de ramassage, mode « Mon pressing » : le client demande à l'un
 * de ses pressings de passer chercher son linge ; le pressing accepte (en
 * gardant ou en changeant le créneau) ou refuse. Les règles métier sont
 * ici ; un refus de règle lève une RuntimeException au message affichable.
 */
class PickupRequests
{
    /**
     * @param  array{pickup_date: string, slot_from: string, slot_to: string, items_estimate?: ?int, note?: ?string}  $data
     */
    public function create(Customer $customer, Pressing $pressing, CustomerAddress $address, array $data): PickupRequest
    {
        if (! $customer->pressings()->whereKey($pressing->id)->exists()) {
            throw new RuntimeException('Ajoutez d\'abord ce pressing à vos pressings.');
        }

        $services = $pressing->deliveryServices();
        if ($services === null || ! $services['pickup']) {
            throw new RuntimeException("{$pressing->name} ne propose pas le ramassage.");
        }

        if ($address->customer_id !== $customer->id) {
            throw new RuntimeException('Adresse introuvable.');
        }

        if (! in_array($address->commune, $services['communes'], true)) {
            throw new RuntimeException("{$pressing->name} ne passe pas à {$address->commune}.");
        }

        $day = collect($pressing->pickupAvailability())->firstWhere('date', $data['pickup_date']);
        $slotIsOffered = $day !== null && collect($day['slots'])
            ->contains(fn (array $slot) => $slot['from'] === $data['slot_from'] && $slot['to'] === $data['slot_to']);
        if (! $slotIsOffered) {
            throw new RuntimeException('Ce créneau n\'est plus disponible. Choisissez-en un autre.');
        }

        return DB::transaction(function () use ($customer, $pressing, $address, $data, $services) {
            // Verrou sur le client : deux envois simultanés ne contournent pas les limites.
            Customer::whereKey($customer->id)->lockForUpdate()->first();

            $open = PickupRequest::where('customer_id', $customer->id)->open();
            if ((clone $open)->where('pressing_id', $pressing->id)->exists()) {
                throw new RuntimeException("Vous avez déjà une demande en cours chez {$pressing->name}.");
            }
            if ($open->count() >= PickupRequest::MAX_OPEN_PER_CUSTOMER) {
                throw new RuntimeException('Vous avez déjà '.PickupRequest::MAX_OPEN_PER_CUSTOMER.' demandes de ramassage en cours.');
            }

            $request = PickupRequest::create([
                'pressing_id' => $pressing->id,
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
                'pickup_fee_fcfa' => $services['pickup_fee_fcfa'],
            ]);

            ActivityLog::record($pressing->id, ActivityAction::PickupRequested,
                "Demande de ramassage de {$customer->fullName()} pour {$request->scheduleLabel()} ({$request->place()})", null, ['pickup_request_id' => $request->id]);

            $this->alertAdmins($request);

            return $request;
        });
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

        $base = PickupRequestPhoto::directoryFor($request->pressing_id, $request->id).'/'.Str::random(40);
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

        ActivityLog::record($request->pressing_id, ActivityAction::PickupCancelled,
            "Ramassage de {$request->customer->fullName()} ({$request->scheduleLabel()}) annulé par le client", null, ['pickup_request_id' => $request->id]);

        // Le pressing avait prévu de passer : il est prévenu.
        if ($wasAccepted) {
            $this->alertAdmins($request, cancelled: true);
        }
    }

    /**
     * Accepte la demande, sur le créneau du client ou sur un autre (« reprogrammer »).
     * Sert aussi à reprogrammer un ramassage déjà prévu.
     */
    public function accept(PickupRequest $request, User $user, ?string $date = null, ?string $slotFrom = null, ?string $slotTo = null): void
    {
        DB::transaction(fn () => $this->acceptLocked($this->lockOpen($request), $user, $date, $slotFrom, $slotTo));
        $request->refresh();
    }

    private function acceptLocked(PickupRequest $locked, User $user, ?string $date, ?string $slotFrom, ?string $slotTo): void
    {
        $moved = ($date !== null && $date !== $locked->pickup_date->toDateString())
            || ($slotFrom !== null && $slotFrom !== $locked->slot_from)
            || ($slotTo !== null && $slotTo !== $locked->slot_to);

        if ($locked->status === PickupRequestStatus::Accepted && ! $moved) {
            return;
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

        ActivityLog::record($locked->pressing_id, ActivityAction::PickupAccepted,
            ($moved ? 'Ramassage reprogrammé' : 'Ramassage accepté')." pour {$locked->customer->fullName()} : {$locked->scheduleLabel()}", null, ['pickup_request_id' => $locked->id]);

        $locked->customer->notify(new PickupAcceptedNotification($locked));
    }

    public function refuse(PickupRequest $request, User $user, string $reason): void
    {
        DB::transaction(fn () => $this->refuseLocked($this->lockOpen($request), $user, $reason));
        $request->refresh();
    }

    private function refuseLocked(PickupRequest $locked, User $user, string $reason): void
    {
        if ($locked->status !== PickupRequestStatus::Pending) {
            throw new RuntimeException('Ce ramassage est déjà prévu : reprogrammez-le, ou appelez le client.');
        }

        $locked->update(['refusal_reason' => mb_substr(trim($reason), 0, 255), 'responded_by' => $user->id, 'responded_at' => now()]);
        $this->close($locked, PickupRequestStatus::Refused);

        ActivityLog::record($locked->pressing_id, ActivityAction::PickupRefused,
            "Ramassage refusé pour {$locked->customer->fullName()} : {$locked->refusal_reason}", null, ['pickup_request_id' => $locked->id]);

        $locked->customer->notify(new PickupRefusedNotification($locked));
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
    private function alertAdmins(PickupRequest $request, bool $cancelled = false): void
    {
        $pressing = $request->pressing;
        if ($pressing->isSandbox()) {
            return;
        }

        $emails = $pressing->admins()->wherePivot('is_active', true)->whereNotNull('email')->pluck('email')->unique()->all();
        if ($emails === []) {
            return;
        }

        defer(function () use ($request, $emails, $cancelled): void {
            try {
                Mail::to($emails)->send(new PickupRequestAlert($request, $cancelled));
            } catch (Throwable $e) {
                Log::warning('Alerte e-mail de ramassage non envoyée', ['pickup_request_id' => $request->id, 'error' => $e->getMessage()]);
            }
        });
    }
}
