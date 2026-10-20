<?php

namespace App\Livewire\Group;

use App\Livewire\Concerns\ShowsToasts;
use App\Livewire\Group\Concerns\InteractsWithGroup;
use App\Support\GroupReport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Rapports du groupe : bilan d'un mois (aperçu, PDF), exports pour le
 * comptable et envoi automatique du rapport chaque début de mois.
 */
class Reports extends Component
{
    use InteractsWithGroup, ShowsToasts;

    #[Url(as: 'mois', except: '')]
    public string $month = '';

    public function toggleMonthlyEmail(): void
    {
        $user = auth()->user();

        if (! $user->email) {
            $this->toast('Ajoutez d\'abord une adresse e-mail dans « Mon compte ».', 'warning');

            return;
        }

        $user->update(['group_report_opt_in' => ! $user->group_report_opt_in]);
        $this->toast($user->group_report_opt_in
            ? 'Vous recevrez le rapport du groupe le 1er de chaque mois à '.$user->email.'.'
            : 'Envoi mensuel du rapport désactivé.');
    }

    #[Layout('layouts.dashboard', ['active' => 'group-reports', 'title' => 'Rapports du groupe'])]
    public function render()
    {
        $month = GroupReport::month($this->month ?: null);
        $months = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        return view('livewire.group.reports', [
            ...GroupReport::data(auth()->user(), $month),
            'months' => $months,
            'monthKey' => $month->format('Y-m'),
            'user' => auth()->user(),
        ]);
    }
}
