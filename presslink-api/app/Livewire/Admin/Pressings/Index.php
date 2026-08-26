<?php

namespace App\Livewire\Admin\Pressings;

use App\Enums\PressingStatus;
use App\Models\Pressing;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggleStatus(int $pressingId): void
    {
        $pressing = Pressing::findOrFail($pressingId);

        $pressing->update([
            'status' => $pressing->status === PressingStatus::Active
                ? PressingStatus::Suspended
                : PressingStatus::Active,
        ]);
    }

    #[Layout('layouts.admin', ['active' => 'pressings', 'title' => 'Pressings'])]
    public function render()
    {
        $query = Pressing::withCount(['staff', 'customers', 'orders']);

        if ($this->search !== '') {
            $term = $this->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%"));
        }

        return view('livewire.admin.pressings.index', [
            'pressings' => $query->latest()->paginate(15),
        ]);
    }
}
