<?php

namespace App\Livewire\Concerns;

/**
 * Notifications de l'interface (composant <x-feedback />) : `toast()` pour
 * un retour immédiat, `flashToast()` quand l'action se termine par une
 * redirection (la notification s'affiche alors sur la page suivante).
 */
trait ShowsToasts
{
    /** @param  'success'|'error'|'warning'|'info'  $type */
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    /** @param  'success'|'error'|'warning'|'info'  $type */
    protected function flashToast(string $message, string $type = 'success'): void
    {
        session()->flash('toast', ['type' => $type, 'message' => $message]);
    }
}
