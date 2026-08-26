<?php

namespace App\Enums;

/**
 * Packs d'abonnement — Modèle économique §2.
 * Prix indicatifs (FCFA/mois) — hypothèses commerciales, pas définitives.
 */
enum SubscriptionPlan: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case Business = 'business';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Pro => 'Pro',
            self::Business => 'Business',
            self::Enterprise => 'Enterprise',
        };
    }

    public function monthlyPriceFcfa(): ?int
    {
        return match ($this) {
            self::Starter => 5_000,
            self::Pro => 10_000,
            self::Business => 20_000,
            self::Enterprise => null,
        };
    }

    /** Quota mensuel de commandes. Null = illimité. */
    public function ordersLimit(): ?int
    {
        return match ($this) {
            self::Starter => 150,
            self::Pro => 500,
            self::Business, self::Enterprise => null,
        };
    }
}
