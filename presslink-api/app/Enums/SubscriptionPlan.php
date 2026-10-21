<?php

namespace App\Enums;

use App\Models\Setting;

/**
 * Formules d'abonnement. Une seule limite : le nombre de commandes par mois
 * (`null` = illimité, dans la limite d'une utilisation normale du service).
 * Prix, commandes par mois et fonctions incluses (App\Enums\Feature) se
 * règlent depuis le Super Admin ; les valeurs ci-dessous servent par défaut.
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

    /**
     * Réglages d'origine de la formule, tant que le Super Admin ne les a pas
     * modifiés (page Formules, réglage « plans »).
     *
     * @return array{monthly: ?int, annual: ?int, orders_limit: ?int, features: list<string>}
     */
    public function defaults(): array
    {
        return match ($this) {
            self::Starter => ['monthly' => 20_000, 'annual' => 200_000, 'orders_limit' => 100, 'features' => []],
            self::Business => ['monthly' => 30_000, 'annual' => 300_000, 'orders_limit' => 200,
                'features' => [Feature::Cash->value, Feature::CustomRoles->value, Feature::ActivityLog->value, Feature::Exports->value, Feature::Offline->value]],
            self::Premium => ['monthly' => 50_000, 'annual' => 500_000, 'orders_limit' => 350,
                'features' => array_map(fn (Feature $feature) => $feature->value, Feature::cases())],
        };
    }

    /** @return array{monthly: ?int, annual: ?int, orders_limit: ?int, features: list<string>} */
    public function settings(): array
    {
        $saved = Setting::read('plans', [])[$this->value] ?? [];

        return array_replace($this->defaults(), array_intersect_key($saved, $this->defaults()));
    }

    /** La formule inclut-elle cette fonction ? */
    public function includes(Feature $feature): bool
    {
        return in_array($feature->value, $this->settings()['features'], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Business => 'Business',
            self::Premium => 'Premium',
        };
    }

    /** Prix mensuel (FCFA), null = sur devis. */
    public function monthlyPriceFcfa(): ?int
    {
        return $this->settings()['monthly'];
    }

    /** Prix annuel (FCFA), null = sur devis. */
    public function annualPriceFcfa(): ?int
    {
        return $this->settings()['annual'];
    }

    /** Commandes par mois. Null = illimité. */
    public function ordersLimit(): ?int
    {
        return $this->settings()['orders_limit'];
    }
}
