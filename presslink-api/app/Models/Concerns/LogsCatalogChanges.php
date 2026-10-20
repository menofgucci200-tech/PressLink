<?php

namespace App\Models\Concerns;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

/**
 * Trace dans le journal du pressing chaque modification du catalogue
 * (services, pièces, variantes, prix) faite par un membre de l'équipe.
 * Le modèle fournit le pressing concerné et la phrase à écrire.
 */
trait LogsCatalogChanges
{
    public static function bootLogsCatalogChanges(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function (self $model) use ($event): void {
                if (! Auth::guard('web')->check()) {
                    return;
                }

                $description = $model->catalogDescription($event);
                $pressingId = $model->catalogPressingId();

                if ($description !== null && $pressingId !== null) {
                    ActivityLog::record($pressingId, ActivityAction::CatalogChanged, $description);
                }
            });
        }
    }

    abstract public function catalogPressingId(): ?int;

    /** Phrase du journal pour cet évènement, ou null s'il n'y a rien à tracer. */
    abstract public function catalogDescription(string $event): ?string;

    /** « désactivé » / « réactivé » quand seul is_active a changé. */
    protected function activationChange(): ?string
    {
        if (! $this->wasChanged('is_active')) {
            return null;
        }

        return $this->is_active ? 'réactivé' : 'désactivé';
    }
}
