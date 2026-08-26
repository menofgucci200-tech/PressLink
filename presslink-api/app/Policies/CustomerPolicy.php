<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * RB-01/RB-08 : le staff ne voit que les clients de ses propres pressings.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $customer->pressings()
            ->whereIn('pressings.id', $user->pressings()->pluck('pressings.id'))
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }
}
