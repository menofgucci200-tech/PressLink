<?php

namespace App\Enums;

use App\Models\Setting;

/**
 * Fonctions réservées à une formule. Un pressing y a accès si sa formule
 * l'inclut, ou s'il l'a prise en option (Subscription::$addons). Tout le
 * reste (comptoir, suivi client, encaissement, catalogue, clients,
 * signalements…) est ouvert dès Starter.
 */
enum Feature: string
{
    case Cash = 'caisse';
    case CustomRoles = 'roles';
    case ActivityLog = 'journal';
    case Exports = 'exports';
    case Group = 'groupe';
    case Offline = 'hors_ligne';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Caisse complète',
            self::CustomRoles => 'Rôles sur mesure',
            self::ActivityLog => "Journal d'activité",
            self::Exports => 'Exports Excel et PDF',
            self::Group => 'Gestion de plusieurs pressings',
            self::Offline => 'Mode hors connexion',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Cash => 'Dépenses du jour, clôture de caisse, écarts et historique.',
            self::CustomRoles => 'Créez vos rôles (Gérant, Caissière…) et cochez les accès de chacun.',
            self::ActivityLog => 'Qui a fait quoi, et quand : commandes, paiements, équipe, caisse.',
            self::Exports => 'Vos commandes et paiements en Excel, CSV ou PDF.',
            self::Group => 'Commandes, caisse, clients, équipe et rapports de tous vos pressings, et tarifs harmonisés.',
            self::Offline => 'Sans Internet, continuez à créer commandes et clients, encaisser et remettre ; tout est envoyé au retour de la connexion.',
        };
    }

    /** Première formule qui inclut cette fonction (null = aucune, option seulement). */
    public function includedFrom(): ?SubscriptionPlan
    {
        foreach (SubscriptionPlan::cases() as $plan) {
            if ($plan->includes($this)) {
                return $plan;
            }
        }

        return null;
    }

    /** Prix de l'option seule, par mois (FCFA) ; null = communiqué par PressLink. */
    public function priceFcfa(): ?int
    {
        $prices = Setting::read('option_prices');

        return is_array($prices) ? ($prices[$this->value] ?? null) : $this->defaultPriceFcfa();
    }

    /** Prix d'origine, tant que le Super Admin ne les a pas réglés. */
    public function defaultPriceFcfa(): ?int
    {
        return match ($this) {
            self::Cash, self::CustomRoles, self::ActivityLog => 2_000,
            self::Exports => 1_000,
            self::Group => 3_000,
            self::Offline => null,
        };
    }

    /** Fonction dont dépend un accès de rôle (null = ouvert à toutes les formules). */
    public static function forPermission(Permission $permission): ?self
    {
        return match ($permission) {
            Permission::CashExpenses, Permission::CashClose, Permission::CashHistory => self::Cash,
            Permission::RolesManage => self::CustomRoles,
            Permission::ActivityView => self::ActivityLog,
            Permission::ReportsExport => self::Exports,
            default => null,
        };
    }
}
