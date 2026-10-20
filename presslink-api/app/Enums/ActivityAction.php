<?php

namespace App\Enums;

/**
 * Actions consignées dans le journal d'activité d'un pressing : qui a fait
 * quoi, et quand (commandes, remises, encaissements, clients, caisse,
 * catalogue, équipe, paramètres, connexions). `isSensitive()` : à surveiller
 * en priorité.
 */
enum ActivityAction: string
{
    // Commandes
    case OrderCreated = 'order_created';
    case OrderStatusChanged = 'order_status_changed';
    case OrderHandedOver = 'order_handed_over';
    case RecoveredUnpaid = 'recovered_unpaid';
    case OrderCancelled = 'order_cancelled';
    case StorageFeeCharged = 'storage_fee_charged';
    case StorageFeeWaived = 'storage_fee_waived';
    case IssueReported = 'issue_reported';
    case IssueResolved = 'issue_resolved';

    // Paiements
    case PaymentRecorded = 'payment_recorded';
    case PaymentRefunded = 'payment_refunded';
    case PaymentRemoved = 'payment_removed';

    // Clients
    case CustomerAdded = 'customer_added';

    // Caisse
    case CashClosed = 'cash_closed';
    case CashReopened = 'cash_reopened';

    // Catalogue et paramètres
    case CatalogChanged = 'catalog_changed';
    case CatalogSynced = 'catalog_synced';
    case SettingsChanged = 'settings_changed';
    case SettingsSynced = 'settings_synced';
    case ExceptionalOpening = 'exceptional_opening';

    // Équipe
    case TeamChanged = 'team_changed';
    case RolesChanged = 'roles_changed';
    case StaffLogin = 'staff_login';

    public function label(): string
    {
        return match ($this) {
            self::OrderCreated => 'Commande créée',
            self::OrderStatusChanged => 'Statut changé',
            self::OrderHandedOver => 'Commande remise',
            self::RecoveredUnpaid => 'Remise sans encaissement',
            self::OrderCancelled => 'Commande annulée',
            self::StorageFeeCharged => 'Droit de magasinage facturé',
            self::StorageFeeWaived => 'Droit de magasinage offert',
            self::IssueReported => 'Signalement reçu',
            self::IssueResolved => 'Signalement résolu',
            self::PaymentRecorded => 'Paiement encaissé',
            self::PaymentRefunded => 'Remboursement',
            self::PaymentRemoved => 'Paiement supprimé',
            self::CustomerAdded => 'Client ajouté',
            self::CashClosed => 'Caisse clôturée',
            self::CashReopened => 'Caisse rouverte',
            self::CatalogChanged => 'Services et prix modifiés',
            self::CatalogSynced => 'Tarifs harmonisés',
            self::SettingsChanged => 'Paramètres modifiés',
            self::SettingsSynced => 'Paramètres harmonisés',
            self::ExceptionalOpening => 'Ouverture exceptionnelle',
            self::TeamChanged => 'Équipe modifiée',
            self::RolesChanged => 'Rôles modifiés',
            self::StaffLogin => 'Connexion',
        };
    }

    /** Catégorie, pour filtrer le journal. */
    public function category(): string
    {
        return match ($this) {
            self::OrderCreated, self::OrderStatusChanged, self::OrderHandedOver, self::RecoveredUnpaid,
            self::OrderCancelled, self::IssueReported, self::IssueResolved => 'orders',
            self::StorageFeeCharged, self::StorageFeeWaived => 'payments',
            self::PaymentRecorded, self::PaymentRefunded, self::PaymentRemoved => 'payments',
            self::CustomerAdded => 'customers',
            self::CashClosed, self::CashReopened => 'cash',
            self::CatalogChanged, self::CatalogSynced, self::SettingsChanged, self::SettingsSynced, self::ExceptionalOpening => 'settings',
            self::TeamChanged, self::RolesChanged, self::StaffLogin => 'team',
        };
    }

    /** @return array<string, string> catégorie => libellé */
    public static function categories(): array
    {
        return [
            'orders' => 'Commandes',
            'payments' => 'Paiements',
            'customers' => 'Clients',
            'cash' => 'Caisse',
            'settings' => 'Services, prix et paramètres',
            'team' => 'Équipe et connexions',
        ];
    }

    /** @return list<string> */
    public static function valuesIn(string $category): array
    {
        return array_values(array_map(fn (self $action) => $action->value, array_filter(self::cases(), fn (self $action) => $action->category() === $category)));
    }

    public function isSensitive(): bool
    {
        return in_array($this, [
            self::OrderCancelled, self::RecoveredUnpaid, self::PaymentRefunded, self::PaymentRemoved, self::StorageFeeWaived,
            self::CashReopened, self::ExceptionalOpening, self::RolesChanged,
        ], true);
    }
}
