<?php

namespace App\Livewire\Pressing;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Pressing;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Badge « Ouvert / Fermé » de l'en-tête. En dehors des horaires, l'accueil
 * des clients est en lecture seule ; l'administrateur peut alors ouvrir
 * exceptionnellement (1 heure ou jusqu'à la fin de la journée), ce qui est
 * inscrit au journal du propriétaire.
 */
class OpenStatus extends Component
{
    /** Durées proposées : 60 minutes, ou « jusqu'à minuit ». */
    public const DURATIONS = ['hour', 'day'];

    #[On('open-exceptionally')]
    public function openExceptionally(string $duration = 'hour'): void
    {
        $pressing = $this->pressing();
        abort_unless($pressing !== null && auth()->user()->isAdminOf($pressing), 403);

        $until = $duration === 'day' ? now()->endOfDay() : now()->addHour();
        $pressing->update(['exceptional_open_until' => $until]);

        ActivityLog::record($pressing->id, ActivityAction::ExceptionalOpening,
            'Ouverture exceptionnelle jusqu\'à '.$until->format('H:i').' par '.auth()->user()->name);

        $this->redirect(url()->previous() ?: route('dashboard'), navigate: true);
    }

    public function closeNow(): void
    {
        $pressing = $this->pressing();
        abort_unless($pressing !== null && auth()->user()->isAdminOf($pressing), 403);

        $pressing->update(['exceptional_open_until' => null]);

        $this->redirect(url()->previous() ?: route('dashboard'), navigate: true);
    }

    private function pressing(): ?Pressing
    {
        return auth()->user()->currentPressing();
    }

    public function render()
    {
        $pressing = $this->pressing();

        return view('livewire.pressing.open-status', [
            'pressing' => $pressing,
            'isAdmin' => $pressing !== null && auth()->user()->isAdminOf($pressing),
        ]);
    }
}
