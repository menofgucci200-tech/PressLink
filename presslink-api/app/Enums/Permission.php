<?php

namespace App\Enums;

/**
 * Accès qu'un rôle peut donner dans un pressing. Voir les commandes et les
 * clients reste ouvert à tout membre de l'équipe ; tout le reste se coche.
 * Le rôle « Propriétaire » a toujours tous les accès (plus la vue groupe et
 * le journal d'activité, propres aux propriétaires).
 */
enum Permission: string
{
    // Accueil et commandes
    case OrdersCreate = 'orders.create';
    case PaymentsRecord = 'payments.record';
    case OrdersWorkshop = 'orders.workshop';
    case OrdersCancel = 'orders.cancel';
    case OrdersCancelInProgress = 'orders.cancel_in_progress';
    case StorageFeeWaive = 'orders.waive_storage_fee';
    case IssuesManage = 'issues.manage';
    case OpenExceptionally = 'pressing.open_exceptionally';

    // Caisse
    case CashOwnPayments = 'cash.own_payments';
    case CashClose = 'cash.close';
    case CashHistory = 'cash.history';

    // Revenus et pilotage
    case RevenueView = 'revenue.view';
    case ReportsExport = 'reports.export';

    // Gestion
    case CatalogManage = 'catalog.manage';
    case TeamManage = 'team.manage';
    case RolesManage = 'roles.manage';
    case ActivityView = 'activity.view';
    case SettingsManage = 'pressing.settings';
    case SubscriptionManage = 'subscription.manage';

    public function label(): string
    {
        return match ($this) {
            self::OrdersCreate => 'Créer des commandes et des clients',
            self::PaymentsRecord => 'Encaisser et remettre les commandes',
            self::OrdersWorkshop => 'Atelier : passer « En traitement » ou « Prête »',
            self::OrdersCancel => 'Annuler une commande « Reçue » ou « En attente »',
            self::OrdersCancelInProgress => 'Annuler une commande « En traitement »',
            self::StorageFeeWaive => 'Offrir le droit de magasinage à la remise',
            self::IssuesManage => 'Traiter les signalements des clients',
            self::OpenExceptionally => 'Ouvrir exceptionnellement hors horaires',
            self::CashOwnPayments => 'Voir ses propres encaissements du jour (sans total)',
            self::CashClose => 'Clôturer la caisse du jour (espèces attendues du jour)',
            self::CashHistory => 'Historique de la caisse, des clôtures et des écarts',
            self::RevenueView => 'Chiffre d\'affaires, encaissements et graphiques',
            self::ReportsExport => 'Exports des commandes (CSV, Excel, PDF)',
            self::CatalogManage => 'Services et prix',
            self::TeamManage => 'Équipe (membres sans accès Équipe)',
            self::RolesManage => 'Rôles et permissions',
            self::ActivityView => 'Journal d\'activité (qui a fait quoi, et quand)',
            self::SettingsManage => 'Profil et paramètres du pressing (logo, horaires, conditions, magasinage)',
            self::SubscriptionManage => 'Abonnement et facturation',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::OrdersCreate, self::PaymentsRecord, self::OrdersWorkshop, self::OrdersCancel,
            self::OrdersCancelInProgress, self::StorageFeeWaive, self::IssuesManage, self::OpenExceptionally => 'Accueil et commandes',
            self::CashOwnPayments, self::CashClose, self::CashHistory => 'Caisse',
            self::RevenueView, self::ReportsExport => 'Revenus et pilotage',
            default => 'Gestion',
        };
    }

    /** Accès sensibles (revenus), signalés à côté de la case. */
    public function isSensitive(): bool
    {
        return in_array($this, [self::CashHistory, self::RevenueView, self::ReportsExport, self::ActivityView], true);
    }

    /** @return array<string, list<self>> accès regroupés pour l'affichage */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
