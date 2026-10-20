<?php

namespace App\Enums;

/**
 * Motif d'annulation d'une commande, obligatoire : il reste dans l'historique,
 * le journal du propriétaire et l'application du client.
 */
enum CancellationReason: string
{
    case EntryError = 'erreur_saisie';
    case Duplicate = 'doublon';
    case CustomerWithdrew = 'client_renonce';
    case ItemRefused = 'piece_refusee';
    case Other = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::EntryError => 'Erreur de saisie',
            self::Duplicate => 'Commande en double',
            self::CustomerWithdrew => 'Le client a renoncé',
            self::ItemRefused => 'Pièce refusée ou irréparable',
            self::Other => 'Autre motif',
        };
    }
}
