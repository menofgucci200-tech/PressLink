<?php

namespace App\Casts;

use App\Enums\SubscriptionPlan;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Lit la colonne `plan` en tolérant les anciennes formules (« pro »,
 * « enterprise ») encore présentes en base d'avant la grille V1, au lieu
 * de lever une ValueError qui ferait tomber tout écran lisant l'abonnement.
 *
 * @implements CastsAttributes<SubscriptionPlan, SubscriptionPlan|string>
 */
class SubscriptionPlanCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?SubscriptionPlan
    {
        return $value === null ? null : SubscriptionPlan::fromStored((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof SubscriptionPlan) {
            return $value->value;
        }

        $plan = is_string($value) ? SubscriptionPlan::tryFrom($value) : null;

        if ($plan === null) {
            throw new InvalidArgumentException('Formule d\'abonnement invalide.');
        }

        return $plan->value;
    }
}
