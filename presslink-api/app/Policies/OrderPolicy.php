<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * RB-01 : un pressing ne doit jamais accéder aux données d'un autre pressing.
 * RB-08 : un employé ne peut accéder qu'aux pressings auxquels il est affecté.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->belongsToPressing($order->pressing);
    }

    public function create(User $user, ?Order $order = null): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->belongsToPressing($order->pressing);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isAdminOf($order->pressing);
    }
}
