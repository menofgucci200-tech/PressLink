<?php

use App\Actions\Auth\ResetPasswordToDefault;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Les clients enregistrés par un pressing ne reçoivent plus le mot de passe
 * par défaut : leur compte attend qu'ils s'inscrivent dans l'appli avec leur
 * numéro. Les comptes déjà créés ainsi et jamais utilisés (mot de passe par
 * défaut, aucune connexion) passent dans cet état.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')
            ->where('must_change_password', true)
            ->whereNull('last_login_at')
            ->whereNotNull('password')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $ids = collect($rows)
                    ->filter(fn ($row) => Hash::check(ResetPasswordToDefault::DEFAULT_PASSWORD, $row->password))
                    ->pluck('id');

                if ($ids->isNotEmpty()) {
                    DB::table('customers')->whereIn('id', $ids)->update(['password' => null, 'must_change_password' => false]);
                }
            });
    }

    public function down(): void
    {
        // Les comptes réclamés depuis ont leur propre mot de passe : rien à rétablir.
    }
};
