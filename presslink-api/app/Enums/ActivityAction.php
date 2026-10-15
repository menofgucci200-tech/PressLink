<?php

namespace App\Enums;

/**
 * Actions consignées dans le journal d'activité (contrôle à distance par le
 * propriétaire du groupe). `isSensitive()` : à surveiller en priorité.
 */
enum ActivityAction: string
{
    case OrderCancelled = 'order_cancelled';
    case RecoveredUnpaid = 'recovered_unpaid';
    case PaymentRecorded = 'payment_recorded';
    case PaymentRemoved = 'payment_removed';
    case CashClosed = 'cash_closed';
    case CatalogSynced = 'catalog_synced';
    case SettingsSynced = 'settings_synced';
    case TeamChanged = 'team_changed';

    public function label(): string
    {
        return match ($this) {
            self::OrderCancelled => 'Commande annulée',
            self::RecoveredUnpaid => 'Remise sans encaissement',
            self::PaymentRecorded => 'Paiement encaissé',
            self::PaymentRemoved => 'Paiement supprimé',
            self::CashClosed => 'Caisse clôturée',
            self::CatalogSynced => 'Tarifs harmonisés',
            self::SettingsSynced => 'Paramètres harmonisés',
            self::TeamChanged => 'Équipe modifiée',
        };
    }

    public function isSensitive(): bool
    {
        return in_array($this, [self::OrderCancelled, self::RecoveredUnpaid, self::PaymentRemoved], true);
    }
}
