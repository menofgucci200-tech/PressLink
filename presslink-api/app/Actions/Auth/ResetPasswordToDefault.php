<?php

namespace App\Actions\Auth;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Réinitialisation d'un mot de passe par l'équipe PressLink ou un
 * administrateur de pressing : le mot de passe devient {@see self::DEFAULT_PASSWORD},
 * les sessions ouvertes sont fermées et le compte est marqué « à changer »
 * jusqu'à ce que son titulaire choisisse un nouveau mot de passe depuis
 * son profil.
 */
class ResetPasswordToDefault
{
    public const DEFAULT_PASSWORD = '123456';

    public function handle(User|Customer $account): void
    {
        DB::transaction(function () use ($account) {
            $account->forceFill([
                'password' => self::DEFAULT_PASSWORD,
                'must_change_password' => true,
            ]);

            if ($account instanceof User) {
                // Déconnecte les autres navigateurs (session et « rester connecté »).
                $account->forceFill(['remember_token' => Str::random(60)]);
                DB::table('sessions')->where('user_id', $account->getKey())->delete();
            } else {
                $account->tokens()->delete();
            }

            $account->save();
        });
    }
}
