<?php

namespace App\Livewire\Activity;

use App\Enums\ActivityAction;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Livewire\Concerns\RequiresFeature;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Support\SpreadsheetSafe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Journal d'activité du pressing : qui a fait quoi, et quand — commandes
 * créées, remises, encaissements, annulations, clients, caisse, catalogue,
 * équipe, paramètres et connexions. Filtrable par membre, type, période.
 */
class Index extends Component
{
    use RequiresFeature, WithPagination;

    #[Url(as: 'membre', except: '')]
    public string $userId = '';

    #[Url(as: 'type', except: '')]
    public string $category = '';

    #[Url(as: 'du', except: '')]
    public string $from = '';

    #[Url(as: 'au', except: '')]
    public string $to = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sensibles', except: false)]
    public bool $sensitiveOnly = false;

    public function mount(): void
    {
        $this->requireFeature(Feature::ActivityLog);
        abort_unless(auth()->user()->may(Permission::ActivityView), 403);
    }

    /** Revérifié à chaque action (export compris). */
    public function hydrate(): void
    {
        abort_unless(auth()->user()->may(Permission::ActivityView), 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['userId', 'category', 'from', 'to', 'search', 'sensitiveOnly'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['userId', 'category', 'from', 'to', 'search', 'sensitiveOnly']);
        $this->resetPage();
    }

    private function pressing(): Pressing
    {
        $pressing = auth()->user()->currentPressing();
        abort_if($pressing === null, 403);

        return $pressing;
    }

    /** @return Builder<ActivityLog> */
    private function query(): Builder
    {
        $date = function (string $value): ?Carbon {
            try {
                return $value !== '' ? Carbon::parse($value) : null;
            } catch (\Exception) {
                return null;
            }
        };

        return ActivityLog::query()
            ->with(['user:id,name', 'order:id,order_number'])
            ->where('pressing_id', $this->pressing()->id)
            ->when($this->userId === 'systeme', fn ($q) => $q->whereNull('user_id'))
            ->when(ctype_digit($this->userId), fn ($q) => $q->where('user_id', (int) $this->userId))
            ->when(array_key_exists($this->category, ActivityAction::categories()), fn ($q) => $q->whereIn('action', ActivityAction::valuesIn($this->category)))
            ->when($this->sensitiveOnly, fn ($q) => $q->whereIn('action', array_map(fn ($a) => $a->value, array_filter(ActivityAction::cases(), fn ($a) => $a->isSensitive()))))
            ->when($date($this->from), fn ($q, Carbon $d) => $q->where('created_at', '>=', $d->copy()->startOfDay()))
            ->when($date($this->to), fn ($q, Carbon $d) => $q->where('created_at', '<=', $d->copy()->endOfDay()))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('description', 'like', '%'.trim($this->search).'%'))
            ->latest('created_at')
            ->latest('id');
    }

    /** Export CSV du journal filtré (Excel l'ouvre directement). */
    public function export(): StreamedResponse
    {
        $pressing = $this->pressing();
        $logs = $this->query()->limit(20000)->get();

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Heure', 'Membre', 'Rôle', 'Action', 'Détail', 'Commande'], ';');
            foreach ($logs as $log) {
                fputcsv($out, SpreadsheetSafe::row([
                    $log->created_at->format('d/m/Y'),
                    $log->created_at->format('H:i:s'),
                    $log->user?->name ?? 'Système / client',
                    $log->authorRole() ?? '',
                    $log->action->label(),
                    $log->description,
                    $log->order?->order_number ?? '',
                ]), ';');
            }
            fclose($out);
        }, 'journal-'.$pressing->code.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    #[Layout('layouts.dashboard', ['active' => 'activity', 'title' => 'Journal d\'activité'])]
    public function render()
    {
        $pressing = $this->pressing();

        return view('livewire.activity.index', [
            'logs' => $this->query()->paginate(50),
            'members' => $pressing->staff()->orderBy('name')->get(['users.id', 'users.name']),
            'categories' => ActivityAction::categories(),
            'hasFilters' => $this->userId !== '' || $this->category !== '' || $this->from !== '' || $this->to !== '' || $this->search !== '' || $this->sensitiveOnly,
        ]);
    }
}
