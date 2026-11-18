<?php

namespace App\Livewire\Admin\Health;

use App\Support\PlatformHealth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Santé de la plateforme (console super admin) : démarrage de PHP, OPcache,
 * base de données, cron, envois en attente, espace disque. Lecture seule.
 */
class Index extends Component
{
    /** Temps entre le début de la requête et l'ouverture de la page. */
    public float $bootMs = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $start = defined('LARAVEL_START') ? LARAVEL_START : (float) request()->server('REQUEST_TIME_FLOAT', microtime(true));
        $this->bootMs = (microtime(true) - $start) * 1000;
    }

    #[Layout('layouts.admin', ['active' => 'health', 'title' => 'Santé de la plateforme'])]
    public function render()
    {
        return view('livewire.admin.health.index', [
            'sections' => app(PlatformHealth::class)->sections($this->bootMs),
        ]);
    }
}
