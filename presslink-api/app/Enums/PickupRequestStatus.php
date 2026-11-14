<?php

namespace App\Enums;

/**
 * Parcours d'une demande de ramassage : envoyée par le client, puis
 * acceptée (ramassage prévu) ou refusée par le pressing. Le client peut
 * l'annuler tant que le linge n'est pas ramassé ; sans réponse avant la
 * fin du créneau, elle expire. Sur place, le coursier marque le linge
 * ramassé (la demande devient une commande) ou le client absent.
 */
enum PickupRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case PickedUp = 'picked_up';
    case Missed = 'missed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Demande envoyée',
            self::Accepted => 'Ramassage prévu',
            self::Refused => 'Refusée',
            self::Cancelled => 'Annulée',
            self::Expired => 'Expirée',
            self::PickedUp => 'Linge ramassé',
            self::Missed => 'Ramassage manqué',
        };
    }

    /** Libellé côté pressing. */
    public function staffLabel(): string
    {
        return match ($this) {
            self::Pending => 'À traiter',
            self::PickedUp => 'Ramassée',
            self::Missed => 'Client absent',
            default => $this->label(),
        };
    }

    /** Encore en cours : le client peut l'annuler, le pressing la traiter. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Accepted], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Pending->value, self::Accepted->value];
    }
}
