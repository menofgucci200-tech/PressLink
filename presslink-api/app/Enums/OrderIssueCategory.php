<?php

namespace App\Enums;

/**
 * Type de problème signalé par le client sur une commande —
 * ex. "il manque une chemise", "ce pantalon n'est pas à moi".
 */
enum OrderIssueCategory: string
{
    case MissingItem = 'missing_item';
    case WrongItem = 'wrong_item';
    case DamagedItem = 'damaged_item';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MissingItem => 'Il manque un article',
            self::WrongItem => 'Un article ne m\'appartient pas',
            self::DamagedItem => 'Un article est abîmé',
            self::Other => 'Autre problème',
        };
    }
}
