<?php

namespace App\Enums;

enum Gender: string
{
    case Homme = 'homme';
    case Femme = 'femme';

    public function label(): string
    {
        return match ($this) {
            self::Homme => 'Homme',
            self::Femme => 'Femme',
        };
    }
}
