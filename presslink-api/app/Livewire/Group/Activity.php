<?php

namespace App\Livewire\Group;

use App\Enums\ActivityAction;
use App\Livewire\Group\Concerns\InteractsWithGroup;
use App\Models\ActivityLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Journal d'activité du groupe : qui a annulé, supprimé un paiement, remis
 * sans encaisser, clôturé la caisse… et dans quel pressing.
 */
class Activity extends Component
{
    use InteractsWithGroup, WithPagination;

    #[Url(as: 'pressing', except: '')]
    public string $pressingId = '';

    #[Url(as: 'action', except: '')]
    public string $action = '';

    #[Url(as: 'sensibles', except: false)]
    public bool $sensitiveOnly = false;

    public function updating(string $property): void
    {
        if (in_array($property, ['pressingId', 'action', 'sensitiveOnly'], true)) {
            $this->resetPage();
        }
    }

    #[Layout('layouts.dashboard', ['active' => 'group-activity', 'title' => 'Journal d\'activité'])]
    public function render()
    {
        $sensitive = array_values(array_filter(ActivityAction::cases(), fn (ActivityAction $a) => $a->isSensitive()));

        $logs = ActivityLog::with(['pressing:id,name', 'user:id,name', 'order:id,order_number,pressing_id'])
            ->whereIn('pressing_id', $this->scopedPressingIds($this->pressingId))
            ->when(ActivityAction::tryFrom($this->action), fn ($q, ActivityAction $a) => $q->where('action', $a->value))
            ->when($this->sensitiveOnly, fn ($q) => $q->whereIn('action', array_map(fn ($a) => $a->value, $sensitive)))
            ->latest('created_at')
            ->latest('id')
            ->paginate(30);

        return view('livewire.group.activity', [
            'logs' => $logs,
            'pressings' => $this->groupPressings(),
            'actions' => ActivityAction::cases(),
        ]);
    }
}
