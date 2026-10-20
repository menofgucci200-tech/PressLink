<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro ivoirien au format stocké « +225 » + 10 chiffres. Par défaut, un
 * mobile (01, 05, 07) : c'est lui qui recevra les SMS et les messages
 * WhatsApp. Les pressings peuvent aussi donner un fixe (21, 25, 27).
 *
 * La valeur doit avoir été passée par PhoneNumber::normalize() avant.
 */
class IvorianPhone implements ValidationRule
{
    public function __construct(private readonly bool $mobileOnly = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (PhoneNumber::isValid(is_string($value) ? $value : null, $this->mobileOnly)) {
            return;
        }

        $digits = preg_replace('/\D/', '', PhoneNumber::national(is_string($value) ? $value : ''));

        if (strlen((string) $digits) !== PhoneNumber::NATIONAL_LENGTH) {
            $fail('Entrez un numéro ivoirien de 10 chiffres, ex. 07 08 12 44 00.');

            return;
        }

        $fail($this->mobileOnly
            ? 'Entrez un numéro de mobile ivoirien : il commence par 01, 05 ou 07.'
            : 'Ce numéro ne correspond pas à un numéro ivoirien (01, 05, 07, 21, 25 ou 27).');
    }
}
