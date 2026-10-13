<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Livewire\Attributes\Layout;

/**
 * Portail de la console super admin (/admin/connexion) : même logique que
 * la connexion staff, mais réservé aux comptes super admin et visuellement
 * distinct de l'espace pressing.
 */
class AdminLogin extends Login
{
    protected function allowsUser(User $user): bool
    {
        return (bool) $user->is_super_admin;
    }

    protected function deniedMessage(): string
    {
        return 'Ce compte n\'a pas accès à la console d\'administration.';
    }

    #[Layout('layouts.guest')]
    public function render()
    {
        return view('livewire.auth.admin-login')->title('Console super admin — PressLink');
    }
}
