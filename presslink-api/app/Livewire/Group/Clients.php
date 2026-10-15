<?php

namespace App\Livewire\Group;

use App\Enums\OrderStatus;
use App\Livewire\Group\Concerns\InteractsWithGroup;
use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Fichier clients du groupe : un client est reconnu dans tous les pressings
 * où il dépose, avec son nombre de commandes et son total dépensé
 * consolidés.
 */
class Clients extends Component
{
    use InteractsWithGroup, WithPagination;

    public const SORTS = ['spent' => 'Total dépensé', 'orders' => 'Commandes', 'recent' => 'Dernière commande', 'name' => 'Nom'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'pressing', except: '')]
    public string $pressingId = '';

    #[Url(as: 'tri', except: 'spent')]
    public string $sort = 'spent';

    #[Url(as: 'plusieurs', except: false)]
    public bool $multiOnly = false;

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'pressingId', 'sort', 'multiOnly'], true)) {
            $this->resetPage();
        }
    }

    #[Layout('layouts.dashboard', ['active' => 'group-clients', 'title' => 'Clients du groupe'])]
    public function render()
    {
        $groupIds = $this->groupPressingIds();
        $ids = $this->scopedPressingIds($this->pressingId);
        $inGroup = fn ($q) => $q->whereIn('pressing_id', $groupIds);

        $customers = Customer::query()
            ->whereHas('pressings', fn ($q) => $q->whereIn('pressings.id', $ids))
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(fn ($w) => $w->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"));
            })
            ->when($this->multiOnly, fn ($q) => $q->whereHas('pressings', fn ($p) => $p->whereIn('pressings.id', $groupIds), '>=', 2))
            ->with(['pressings' => fn ($q) => $q->whereIn('pressings.id', $groupIds)->select('pressings.id', 'pressings.name')])
            ->withCount(['orders as group_orders_count' => $inGroup])
            ->withSum(['orders as group_spent' => fn ($q) => $inGroup($q)->where('status', '!=', OrderStatus::Annulee->value)], 'total_fcfa')
            ->withMax(['orders as group_last_order_at' => $inGroup], 'created_at');

        match ($this->sort) {
            'orders' => $customers->orderByDesc('group_orders_count'),
            'recent' => $customers->orderByDesc('group_last_order_at'),
            'name' => $customers->orderBy('last_name')->orderBy('first_name'),
            default => $customers->orderByDesc('group_spent'),
        };

        return view('livewire.group.clients', [
            'customers' => $customers->orderBy('customers.id')->paginate(25),
            'pressings' => $this->groupPressings(),
            'sorts' => self::SORTS,
            'totalCustomers' => Customer::whereHas('pressings', fn ($q) => $q->whereIn('pressings.id', $groupIds))->count(),
            'sharedCustomers' => Customer::whereHas('pressings', fn ($q) => $q->whereIn('pressings.id', $groupIds), '>=', 2)->count(),
        ]);
    }
}
