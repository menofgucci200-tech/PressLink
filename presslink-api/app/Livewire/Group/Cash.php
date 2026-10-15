<?php

namespace App\Livewire\Group;

use App\Actions\Cash\CashDaySummary;
use App\Livewire\Group\Concerns\InteractsWithGroup;
use App\Models\CashClosing;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Caisse du groupe : la journée en cours de chaque pressing (encaissé,
 * clôturée ou non) et l'historique des clôtures avec leurs écarts.
 */
class Cash extends Component
{
    use InteractsWithGroup, WithPagination;

    #[Url(as: 'pressing', except: '')]
    public string $pressingId = '';

    #[Url(as: 'ecarts', except: false)]
    public bool $differencesOnly = false;

    public function updating(string $property): void
    {
        if (in_array($property, ['pressingId', 'differencesOnly'], true)) {
            $this->resetPage();
        }
    }

    #[Layout('layouts.dashboard', ['active' => 'group-cash', 'title' => 'Caisse du groupe'])]
    public function render()
    {
        $pressings = $this->groupPressings();
        $ids = $this->scopedPressingIds($this->pressingId);
        $closedToday = CashClosing::whereIn('pressing_id', $pressings->pluck('id'))->whereDate('business_date', today())->get()->keyBy('pressing_id');

        $today = $pressings->map(fn ($p) => [
            'pressing' => $p,
            'summary' => CashDaySummary::for($p, today()),
            'closing' => $closedToday->get($p->id),
        ]);

        $closings = CashClosing::with(['pressing:id,name', 'closer:id,name'])
            ->whereIn('pressing_id', $ids)
            ->when($this->differencesOnly, fn ($q) => $q->where('difference_fcfa', '!=', 0))
            ->orderByDesc('business_date')
            ->orderBy('pressing_id')
            ->paginate(25);

        $since = today()->subDays(29);
        $month = CashClosing::whereIn('pressing_id', $ids)->where('business_date', '>=', $since);

        return view('livewire.group.cash', [
            'pressings' => $pressings,
            'today' => $today,
            'closings' => $closings,
            'monthDifference' => (int) (clone $month)->sum('difference_fcfa'),
            'monthClosings' => (clone $month)->count(),
            'monthWithDifference' => (clone $month)->where('difference_fcfa', '!=', 0)->count(),
            'since' => Carbon::parse($since),
        ]);
    }
}
