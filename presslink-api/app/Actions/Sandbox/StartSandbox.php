<?php

namespace App\Actions\Sandbox;

use App\Actions\Demo\FillDemoPressing;
use App\Actions\Demo\ResetDemoPressing;
use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Models\SandboxTrial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * « Découvrir avec un pressing fictif » : crée le pressing de test d'un
 * visiteur, déjà rempli (pièces et prix, clients, commandes à chaque étape),
 * avec toutes les fonctions (Premium) pendant SandboxTrial::DAYS jours. Le
 * visiteur en devient le gérant, sans mot de passe à retenir : il est
 * connecté directement. Rien n'y compte dans les statistiques, aucun message
 * n'en part, et tout est supprimé à l'expiration.
 */
class StartSandbox
{
    /** Durée d'un test, en jours. */
    public const DAYS = 3;

    /**
     * @param  string  $contactPhone  WhatsApp du gérant, déjà normalisé (+225…)
     * @return array{manager: User, trial: SandboxTrial}
     */
    public function handle(string $pressingName, string $contactPhone, ?string $ipAddress = null): array
    {
        $expiresAt = now()->addDays(self::DAYS);

        // Un test n'a pas à remplir le journal d'activité de commandes fictives.
        return ActivityLog::withoutLogging(fn () => DB::transaction(function () use ($pressingName, $contactPhone, $ipAddress, $expiresAt) {
            $pressing = Pressing::create([
                'name' => $pressingName,
                'phone' => $contactPhone,
                'address' => 'Adresse de votre pressing',
                'city' => 'Abidjan',
                'description' => 'Pressing de test : les clients et les commandes sont fictifs.',
                'opening_hours' => FillDemoPressing::openingHours(),
                'show_pricing_to_customers' => true,
                'status' => PressingStatus::Active,
                'is_demo' => true,
                'sandbox_expires_at' => $expiresAt,
            ]);

            $manager = User::create(['name' => 'Gérant', 'login' => self::newLogin(), 'password' => Str::password(32)]);
            $employee = User::create(['name' => 'Awa Koffi', 'login' => self::newLogin(), 'password' => Str::password(32)]);
            $pressing->staff()->attach($manager, ['role' => PressingRole::Admin->value, 'is_active' => true]);
            $pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);

            $pressing->subscription()->create([
                'plan' => SubscriptionPlan::Premium,
                'status' => SubscriptionStatus::Active,
                'billing_cycle' => 'monthly',
                'orders_limit' => null,
                'orders_used' => 0,
                'orders_month' => now()->format('Y-m'),
                'current_period_starts_at' => now(),
                'current_period_ends_at' => $expiresAt,
            ]);

            // Mêmes clients fictifs que la démo, rangés dans le fichier du pressing de test.
            (new FillDemoPressing)->handle($pressing, ResetDemoPressing::CUSTOMERS);

            $trial = SandboxTrial::create([
                'pressing_id' => $pressing->id,
                'pressing_name' => $pressingName,
                'contact_phone' => $contactPhone,
                'ip_address' => $ipAddress,
                'expires_at' => $expiresAt,
            ]);

            return ['manager' => $manager, 'trial' => $trial];
        }));
    }

    /** Identifiant de connexion d'un compte de test (jamais saisi par le visiteur). */
    private static function newLogin(): string
    {
        do {
            $login = 'test-'.Str::lower(Str::random(10));
        } while (User::where('login', $login)->exists());

        return $login;
    }
}
