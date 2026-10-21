<?php

namespace App\Support;

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;
use App\Models\Setting;
use App\Models\Subscription;

/**
 * Les formules telles qu'on les présente (page Formules de l'espace pressing
 * et page Tarifs du site) : à qui elles s'adressent, leur prix, leurs
 * commandes par mois et ce qu'elles ajoutent à la formule précédente.
 * Prix, commandes et fonctions viennent des réglages du Super Admin.
 */
final class PlanCatalog
{
    /** Dans toutes les formules. */
    public const ESSENTIALS = [
        'Commandes au comptoir et reçus imprimés',
        'Appli client : suivi des commandes et notification « prête »',
        'Encaissement : espèces, Wave, Orange Money, MTN, Moov, carte',
        'Services, pièces et prix de chaque pièce',
        'Fiches clients et historique',
        'Signalements des clients',
        'Chiffres du jour sur le tableau de bord',
        'Reçus partagés sur WhatsApp',
        'Support par téléphone et WhatsApp',
    ];

    /** @return array{tagline: string, audience: string} */
    public static function pitch(SubscriptionPlan $plan): array
    {
        return match ($plan) {
            SubscriptionPlan::Starter => ['tagline' => 'Digitaliser son pressing', 'audience' => 'Petit pressing, équipe réduite : l\'essentiel pour gérer et tenir ses clients informés.'],
            SubscriptionPlan::Business => ['tagline' => 'Développer son pressing', 'audience' => 'Pressing établi avec plusieurs employés : plus de contrôle sur l\'argent et l\'équipe.'],
            SubscriptionPlan::Premium => ['tagline' => 'Piloter plusieurs pressings', 'audience' => 'Plusieurs établissements : tout le groupe depuis un seul écran.'],
        };
    }

    /**
     * @return list<array{plan: SubscriptionPlan, label: string, tagline: string, audience: string, monthly: ?int, annual: ?int, ordersLimit: ?int, features: list<Feature>, added: list<Feature>, previous: ?SubscriptionPlan, recommended: bool}>
     */
    public static function plans(): array
    {
        $plans = [];
        $previous = null;

        foreach (SubscriptionPlan::cases() as $plan) {
            $features = array_values(array_filter(Feature::cases(), fn (Feature $f) => $plan->includes($f)));
            $monthly = $plan->monthlyPriceFcfa();
            $annual = $plan->annualPriceFcfa();

            $plans[] = [
                'plan' => $plan,
                'label' => $plan->label(),
                ...self::pitch($plan),
                'monthly' => $monthly,
                'annual' => $annual,
                'ordersLimit' => $plan->ordersLimit(),
                'features' => $features,
                // Ce que la formule ajoute à la précédente.
                'added' => $previous === null ? $features : array_values(array_filter($features, fn (Feature $f) => ! $previous->includes($f))),
                'previous' => $previous,
                'recommended' => $plan === SubscriptionPlan::Business,
            ];
            $previous = $plan;
        }

        return $plans;
    }

    /**
     * Ce qu'un pressing gagne en passant de sa formule à une autre.
     *
     * @return array{features: list<Feature>, orders: ?string}
     */
    public static function gains(SubscriptionPlan $from, SubscriptionPlan $to): array
    {
        $features = array_values(array_filter(Feature::cases(), fn (Feature $f) => $to->includes($f) && ! $from->includes($f)));

        $orders = null;
        if ($from->ordersLimit() !== $to->ordersLimit()) {
            $orders = $to->ordersLimit() === null
                ? 'Commandes illimitées'
                : number_format($to->ordersLimit(), 0, ',', ' ').' commandes par mois au lieu de '.number_format((int) $from->ordersLimit(), 0, ',', ' ');
        }

        return ['features' => $features, 'orders' => $orders];
    }

    /** Offre de lancement affichée avec les formules (vide = aucune), réglée par le Super Admin. */
    public const DEFAULT_LAUNCH_OFFER = '1 mois offert pour nos 10 premiers pressings, quelle que soit la formule.';

    public static function launchOffer(): ?string
    {
        $offer = trim((string) Setting::read('launch_offer', self::DEFAULT_LAUNCH_OFFER));

        return $offer === '' ? null : $offer;
    }

    public static function trialDays(): int
    {
        return Subscription::trialDays();
    }

    public static function fcfa(?int $amount): string
    {
        return number_format((int) $amount, 0, ',', ' ');
    }
}
