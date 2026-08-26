<?php

namespace App\Livewire\Admin\Pressings;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Pressing;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Onboarding d'un nouveau pressing par le Super Admin — Phase 7.
 * Crée le pressing (avec son code, généré ou saisi) et son abonnement
 * d'essai (14 jours, plan Starter). Le pressing n'a pas encore
 * d'administrateur : on lui en assigne un depuis le menu Administrateurs.
 */
class Create extends Component
{
    public string $name = '';

    public string $code = '';

    public string $phone = '';

    public string $email = '';

    public string $city = '';

    public string $address = '';

    public ?Pressing $createdPressing = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:20', 'alpha_dash', 'unique:pressings,code'],
            'phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $pressing = DB::transaction(function () {
            $pressing = Pressing::create([
                'name' => $this->name,
                'code' => $this->code !== '' ? mb_strtoupper($this->code) : null,
                'phone' => $this->phone,
                'email' => $this->email ?: null,
                'city' => $this->city ?: null,
                'address' => $this->address ?: null,
            ]);

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
        $this->reset(['name', 'code', 'phone', 'email', 'city', 'address']);
    }

    #[Layout('layouts.admin', ['active' => 'pressings', 'title' => 'Nouveau pressing'])]
    public function render()
    {
        return view('livewire.admin.pressings.create');
    }
}
