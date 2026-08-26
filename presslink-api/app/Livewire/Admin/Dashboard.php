<?php

namespace App\Livewire\Admin;

use App\Enums\PressingStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vue globale du back-office Super Admin — Phase 7 du plan de
 * développement (pressings actifs, clients, commandes).
 */
class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    #[Layout('layouts.admin', ['active' => 'dashboard', 'title' => 'Vue globale'])]
    public function render()
    {
        return view('livewire.admin.dashboard', [
            'activePressings' => Pressing::where('status', PressingStatus::Active)->count(),
            'suspendedPressings' => Pressing::where('status', PressingStatus::Suspended)->count(),
            'totalClients' => Customer::count(),
            'totalOrders' => Order::count(),
            'ordersThisMonth' => Order::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'recentPressings' => Pressing::latest()->take(5)->get(),
        ]);
    }
}
