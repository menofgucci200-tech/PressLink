<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remet au format unique « +225 » + 10 chiffres les numéros déjà
 * enregistrés (clients, équipe, pressings) : espaces retirés, « 00225 »
 * remplacé par « +225 », et le 0 de tête rajouté quand l'ancienne appli
 * l'avait perdu (« +225708124400 » devient « +2250708124400 »).
 *
 * Un numéro qui ne peut pas être corrigé sans risque reste tel quel, de même
 * qu'un numéro dont la forme corrigée appartient déjà à un autre compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'users', 'pressings'] as $table) {
            $unique = $table !== 'pressings';

            DB::table($table)->whereNotNull('phone')->orderBy('id')->chunkById(500, function ($rows) use ($table, $unique) {
                foreach ($rows as $row) {
                    $fixed = self::fix($row->phone);

                    if ($fixed === null || $fixed === $row->phone) {
                        continue;
                    }

                    if ($unique && DB::table($table)->where('phone', $fixed)->where('id', '!=', $row->id)->exists()) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update(['phone' => $fixed]);
                }
            });
        }
    }

    public function down(): void
    {
        // Rien à annuler : les anciens formats n'étaient pas valides.
    }

    private static function fix(string $phone): ?string
    {
        $normalized = PhoneNumber::normalize($phone);
        if (PhoneNumber::isValid($normalized, mobileOnly: false)) {
            return $normalized;
        }

        // Ancienne appli : indicatif + numéro tapé sans son 0 de tête.
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (preg_match('/^225([1-9]\d{8})$/', $digits, $match) === 1) {
            $candidate = PhoneNumber::PREFIX.'0'.$match[1];

            return PhoneNumber::isValid($candidate, mobileOnly: false) ? $candidate : null;
        }

        return null;
    }
};
