<?php

namespace App\Actions\Demo;

use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * (Ré)initialise le pressing de démonstration en production : supprime
 * l'ancienne démo puis recrée un pressing complet (équipe, services, clients,
 * commandes à chaque statut). Sans factories : Faker n'est pas installé en
 * production. Un nouveau mot de passe, commun à tous les comptes de démo,
 * est généré à chaque réinitialisation.
 */
class ResetDemoPressing
{
    public const PRESSING_CODE = 'DM-0001';

    public const MANAGER_LOGIN = 'demo.gerant';

    public const EMPLOYEE_LOGIN = 'demo.employe';

    /**
     * Comptes clients de démo (numéros +225 00… : jamais attribués à un abonné).
     *
     * @var list<array{first_name: string, last_name: string, phone: string, gender: string}>
     */
    public const CUSTOMERS = [
        ['first_name' => 'Aïcha', 'last_name' => 'Koné', 'phone' => '+2250000000001', 'gender' => 'femme'],
        ['first_name' => 'Yao', 'last_name' => 'Kouamé', 'phone' => '+2250000000002', 'gender' => 'homme'],
        ['first_name' => 'Mariam', 'last_name' => 'Traoré', 'phone' => '+2250000000003', 'gender' => 'femme'],
        ['first_name' => 'Serge', 'last_name' => 'N\'Guessan', 'phone' => '+2250000000004', 'gender' => 'homme'],
    ];

    /**
     * @return array{password: string, pressing: Pressing}
     */
    public function handle(): array
    {
        // La remise à zéro de la démo n'a pas à remplir son journal d'activité.
        return ActivityLog::withoutLogging(fn () => $this->reset());
    }

    /** @return array{password: string, pressing: Pressing} */
    private function reset(): array
    {
        $password = Str::password(10, symbols: false);

        $pressing = DB::transaction(function () use ($password): Pressing {
            $this->deleteExistingDemo();

            $pressing = Pressing::create([
                'name' => 'Pressing Démo PressLink',
                'code' => self::PRESSING_CODE,
                'phone' => config('presslink.support.phone_display'),
                'email' => config('presslink.support.email'),
                'address' => 'Rue des Jardins',
                'city' => 'Cocody, Abidjan',
                'description' => 'Pressing de démonstration : explorez PressLink librement, les données sont fictives.',
                'opening_hours' => FillDemoPressing::openingHours(),
                'show_pricing_to_customers' => true,
                'status' => PressingStatus::Active,
                'is_demo' => true,
            ]);

            $manager = User::create(['name' => 'Gérant Démo', 'login' => self::MANAGER_LOGIN, 'password' => $password]);
            $employee = User::create(['name' => 'Employé Démo', 'login' => self::EMPLOYEE_LOGIN, 'password' => $password]);
            $pressing->staff()->attach($manager, ['role' => PressingRole::Admin->value, 'is_active' => true]);
            $pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);

            $pressing->subscription()->create([
                'plan' => SubscriptionPlan::Premium,
                'status' => SubscriptionStatus::Active,
                'billing_cycle' => 'monthly',
                'orders_limit' => null,
                'orders_used' => 0,
                'orders_month' => now()->format('Y-m'),
                'current_period_starts_at' => now()->startOfMonth(),
                'current_period_ends_at' => now()->addYear(),
            ]);

            (new FillDemoPressing)->handle($pressing, self::CUSTOMERS, customerPassword: $password);

            return $pressing;
        });

        return ['password' => $password, 'pressing' => $pressing];
    }

    public static function exists(): bool
    {
        return Pressing::where('code', self::PRESSING_CODE)->exists();
    }

    private function deleteExistingDemo(): void
    {
        // Les mêmes numéros fictifs servent aux pressings de test : seuls ceux de la démo partent.
        $customers = Customer::outsideSandboxes()->whereIn('phone', array_column(self::CUSTOMERS, 'phone'))->get();
        DB::table('notifications')
            ->where('notifiable_type', (new Customer)->getMorphClass())
            ->whereIn('notifiable_id', $customers->pluck('id'))
            ->delete();
        $customers->each->forceDelete();

        User::whereIn('login', [self::MANAGER_LOGIN, self::EMPLOYEE_LOGIN])->get()->each->delete();

        // Les commandes, services, abonnement et rattachements suivent en cascade.
        Pressing::where('code', self::PRESSING_CODE)->get()->each->delete();
    }
}
