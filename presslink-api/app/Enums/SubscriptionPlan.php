<?php

namespace App\Enums;

/**
 * Formules d'abonnement. Une seule limite : le nombre de commandes par mois
 * (`null` = illimité, dans la limite d'une utilisation normale du service).
 * Les fonctions réservées à une formule sont décrites par App\Enums\Feature.
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

    /** La formule inclut-elle cette fonction ? (Business inclut tout Starter, Premium tout Business.) */
    public function includes(Feature $feature): bool
    {
        $order = array_flip(array_map(fn (self $plan) => $plan->value, self::cases()));

        return $order[$this->value] >= $order[$feature->includedFrom()->value];
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
}
