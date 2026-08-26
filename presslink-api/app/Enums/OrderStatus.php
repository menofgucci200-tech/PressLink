<?php

namespace App\Enums;

/**
 * Workflow standard d'une commande — Cahier des fonctionnalités §8.
 */
enum OrderStatus: string
{
    case Recue = 'recue';
    case Traitement = 'traitement';
    case Prete = 'prete';
    case Recuperee = 'recuperee';
    case Attente = 'attente';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Recue => 'Reçue',
            self::Traitement => 'En traitement',
            self::Prete => 'Prête',
            self::Recuperee => 'Récupérée',
            self::Attente => 'En attente',
            self::Annulee => 'Annulée',
        };
    }

    /**
     * Transitions autorisées depuis ce statut (RB-07 : seule une commande
     * PRÊTE peut normalement être marquée RÉCUPÉRÉE).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Recue => [self::Traitement, self::Attente, self::Annulee],
            self::Traitement => [self::Prete, self::Attente, self::Annulee],
            self::Attente => [self::Traitement, self::Annulee],
            self::Prete => [self::Recuperee, self::Annulee],
            self::Recuperee => [],
            self::Annulee => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }
}
