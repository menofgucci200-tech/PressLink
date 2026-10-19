<?php

namespace App\Http\Requests\Concerns;

use App\Support\PhoneNumber;

/**
 * Met le champ « phone » au format stocké (+225 + 10 chiffres) avant la
 * validation : l'appli peut envoyer « 0708124400 » comme « +2250708124400 ».
 */
trait NormalizesPhone
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize((string) $this->input('phone'))]);
        }
    }
}
