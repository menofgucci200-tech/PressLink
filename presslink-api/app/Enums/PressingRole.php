<?php

namespace App\Enums;

/**
 * Rôles d'un membre du staff au sein d'un pressing — Cahier §12.
 */
enum PressingRole: string
{
    case Admin = 'admin';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Employee => 'Employé',
        };
    }
}
