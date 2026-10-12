<?php

namespace App\Enums;

/**
 * Formules d'abonnement — Offre commerciale PressLink V1 (ticket d'entrée +
 * abonnement mensuel/annuel, limites par ressource plutôt que par
 * fonctionnalité cœur). `null` signifie « illimité, dans la limite d'une
 * utilisation normale du service ».
 */
enum SubscriptionPlan: string
{
    case Starter = 'starter';
    case Business = 'business';
    case Premium = 'premium';

    /**
     * Valeur lue en base → formule. Les anciennes formules d'avant la grille
     * V1 sont rattachées à leur équivalent actuel (Pro → Business,
     * Enterprise → Premium) ; toute valeur inconnue retombe sur Starter.
     */
    public static function fromStored(string $value): self
    {
        return match ($value) {
            'pro' => self::Business,
            'enterprise' => self::Premium,
            default => self::tryFrom($value) ?? self::Starter,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Business => 'Business',
            self::Premium => 'Premium',
        };
    }

    /** Ticket d'entrée (FCFA), payé une seule fois à l'installation. */
    public function setupFeeFcfa(): int
    {
        return match ($this) {
            self::Starter => 100_000,
            self::Business => 200_000,
            self::Premium => 350_000,
        };
    }

    public function monthlyPriceFcfa(): ?int
    {
        return match ($this) {
            self::Starter => 20_000,
            self::Business => 30_000,
            self::Premium => 50_000,
        };
    }

    /** Tarif annuel (≈ 10 mois payés sur 12). */
    public function annualPriceFcfa(): ?int
    {
        return match ($this) {
            self::Starter => 200_000,
            self::Business => 300_000,
            self::Premium => 500_000,
        };
    }

    /** Quota mensuel de commandes. Null = illimité. */
    public function ordersLimit(): ?int
    {
        return match ($this) {
            self::Starter => 1_500,
            self::Business => 5_000,
            self::Premium => null,
        };
    }

    /** Nombre d'employés (hors administrateurs). Null = illimité. */
    public function employeesLimit(): ?int
    {
        return match ($this) {
            self::Starter => 3,
            self::Business => 10,
            self::Premium => null,
        };
    }

    /** Nombre de comptes administrateur. Null = illimité. */
    public function adminsLimit(): ?int
    {
        return match ($this) {
            self::Starter => 1,
            self::Business => 3,
            self::Premium => null,
        };
    }

    /** Nombre de clients du pressing. Null = illimité. */
    public function clientsLimit(): ?int
    {
        return match ($this) {
            self::Starter => 500,
            self::Business => 2_000,
            self::Premium => null,
        };
    }

    /** Nombre de services/tarifs. Null = illimité. */
    public function servicesLimit(): ?int
    {
        return match ($this) {
            self::Starter => 20,
            self::Business => 50,
            self::Premium => null,
        };
    }

    /** Nombre de variantes de service. Null = illimité. */
    public function variantsLimit(): ?int
    {
        return match ($this) {
            self::Starter => 50,
            self::Business => 150,
            self::Premium => null,
        };
    }

    /** Historique des commandes conservé, en mois. Null = illimité. */
    public function historyMonths(): ?int
    {
        return match ($this) {
            self::Starter => 12,
            self::Business => 24,
            self::Premium => null,
        };
    }

    /** Import initial de clients existants (Business et au-dessus). */
    public function allowsClientImport(): bool
    {
        return $this !== self::Starter;
    }

    /** KPIs, rapports et analytics avancés (Business et au-dessus). */
    public function hasAdvancedAnalytics(): bool
    {
        return $this !== self::Starter;
    }

    /** Plusieurs pressings gérés depuis un même compte (Premium uniquement). */
    public function allowsMultiPressing(): bool
    {
        return $this === self::Premium;
    }

    /** Tableau de bord consolidé multi-établissements (Premium uniquement). */
    public function hasMutualizedDashboard(): bool
    {
        return $this === self::Premium;
    }
}
