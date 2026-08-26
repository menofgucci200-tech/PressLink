<?php

namespace App\Livewire\Admin;

use App\Enums\PressingStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vue globale du back-office Super Admin — Phase 7 du plan de
 * développement (pressings actifs, clients, commandes), enrichie de
 * filtres (période, ville, administrateur) au-delà du périmètre MVP.
 */
class Dashboard extends Component
{
    public string $period = '30';

    public string $city = '';

    public string $administratorId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    private function periodStart(): ?Carbon
    {
        return match ($this->period) {
            'today' => now()->startOfDay(),
            '7' => now()->subDays(7),
            '30' => now()->subDays(30),
            'month' => now()->startOfMonth(),
            default => null,
        };
    }

    #[Layout('layouts.admin', ['active' => 'dashboard', 'title' => 'Vue globale'])]
    public function render()
    {
        $pressingsQuery = Pressing::query();

        if ($this->city !== '') {
            $pressingsQuery->where('city', $this->city);
        }

        if ($this->administratorId !== '') {
            $administratorId = $this->administratorId;
            $pressingsQuery->whereHas('staff', fn ($q) => $q->where('users.id', $administratorId)
                ->where('pressing_users.role', 'admin')
                ->where('pressing_users.is_active', true));
        }

        $pressingIds = (clone $pressingsQuery)->pluck('id');

        $ordersQuery = Order::whereIn('pressing_id', $pressingIds);
        if ($since = $this->periodStart()) {
            $ordersQuery->where('created_at', '>=', $since);
        }

        return view('livewire.admin.dashboard', [
            'activePressings' => (clone $pressingsQuery)->where('status', PressingStatus::Active)->count(),
            'suspendedPressings' => (clone $pressingsQuery)->where('status', PressingStatus::Suspended)->count(),
            'totalClients' => Customer::whereHas('pressings', fn ($q) => $q->whereIn('pressings.id', $pressingIds))->count(),
            'totalOrders' => (clone $ordersQuery)->count(),
            'recentPressings' => (clone $pressingsQuery)->latest()->take(5)->get(),
            'cities' => Pressing::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'administrators' => User::whereHas('pressings', fn ($q) => $q->where('pressing_users.role', 'admin')
                ->where('pressing_users.is_active', true))->orderBy('name')->get(),
        ]);
    }
}
