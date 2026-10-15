<?php

namespace App\Livewire\Group\Concerns;

use App\Models\Pressing;
use Illuminate\Support\Collection;

/**
 * Pages « Groupe » : réservées à qui administre au moins deux pressings, et
 * limitées à ces pressings-là (un responsable de zone ne voit que sa zone).
 */
trait InteractsWithGroup
{
    public function bootInteractsWithGroup(): void
    {
        abort_unless(auth()->user()?->managesGroup(), 403);

        // Les pages du groupe s'affichent en « vue d'ensemble » (menu du groupe).
        session()->forget('active_pressing_id');
    }

    /** @return Collection<int, Pressing> */
    protected function groupPressings(): Collection
    {
        return auth()->user()->managedPressings();
    }

    /** @return list<int> */
    protected function groupPressingIds(): array
    {
        return auth()->user()->managedPressingIds();
    }

    /** Un pressing du groupe choisi dans un filtre, sinon tous. */
    protected function scopedPressingIds(string $pressingId): array
    {
        $ids = $this->groupPressingIds();

        return $pressingId !== '' && in_array((int) $pressingId, $ids, true) ? [(int) $pressingId] : $ids;
    }
}
