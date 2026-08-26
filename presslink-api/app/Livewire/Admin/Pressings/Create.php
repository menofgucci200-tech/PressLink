<?php

namespace App\Livewire\Admin\Pressings;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Onboarding d'un nouveau pressing par le Super Admin — Phase 7.
 * Crée le pressing, son abonnement d'essai (14 jours, plan Starter) et le
 * premier compte administrateur (mot de passe généré, affiché une fois).
 */
class Create extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $city = '';

    public string $address = '';

    public string $adminName = '';

    public string $adminEmail = '';

    public string $adminPhone = '';

    public ?string $generatedPassword = null;

    public ?Pressing $createdPressing = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'adminName' => ['required', 'string', 'max:100'],
            'adminEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'adminPhone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/', 'unique:users,phone'],
        ]);

        $password = Str::password(10);

        $pressing = DB::transaction(function () use ($password) {
            $pressing = Pressing::create([
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email ?: null,
                'city' => $this->city ?: null,
                'address' => $this->address ?: null,
            ]);

            $admin = User::create([
                'name' => $this->adminName,
                'email' => $this->adminEmail,
                'phone' => $this->adminPhone,
                'password' => $password,
            ]);

            $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

            Subscription::create([
                'pressing_id' => $pressing->id,
                'plan' => SubscriptionPlan::Starter,
                'status' => SubscriptionStatus::Trialing,
                'orders_limit' => SubscriptionPlan::Starter->ordersLimit(),
                'trial_ends_at' => now()->addDays(14),
                'current_period_starts_at' => now(),
                'current_period_ends_at' => now()->addDays(14),
            ]);

            return $pressing;
        });

        $this->createdPressing = $pressing;
        $this->generatedPassword = $password;
        $this->reset(['name', 'phone', 'email', 'city', 'address', 'adminName', 'adminEmail', 'adminPhone']);
    }

    #[Layout('layouts.admin', ['active' => 'pressings', 'title' => 'Nouveau pressing'])]
    public function render()
    {
        return view('livewire.admin.pressings.create');
    }
}
