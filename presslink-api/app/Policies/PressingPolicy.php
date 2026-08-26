<?php

namespace App\Policies;

use App\Models\Pressing;
use App\Models\User;

/**
 * RB-01 : isolation stricte entre pressings.
 * RB-08 : un employé n'accède qu'aux pressings auxquels il est affecté ;
 * seul un admin peut modifier les paramètres du pressing.
 */
class PressingPolicy
{
    public function view(User $user, Pressing $pressing): bool
    {
        return $user->belongsToPressing($pressing);
    }

    public function update(User $user, Pressing $pressing): bool
    {
        return $user->isAdminOf($pressing);
    }

    public function manageTeam(User $user, Pressing $pressing): bool
    {
        return $user->isAdminOf($pressing);
    }

    public function manageSubscription(User $user, Pressing $pressing): bool
    {
        return $user->isAdminOf($pressing);
    }
}
