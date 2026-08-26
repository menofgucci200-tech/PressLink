<?php

namespace App\Enums;

enum OrderIssueStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'En attente',
            self::Resolved => 'Résolu',
        };
    }
}
