<?php

namespace App\Livewire\Services;

use App\Models\Service;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Index extends Component
{
    public bool $showCreateForm = false;

    public string $name = '';

    public string $priceFcfa = '';

    /** @var array<int, array{name: string, priceFcfa: string}> */
    public array $variantRows = [];

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->isAdminOf($pressing), 403);
    }

    public function addVariantRow(): void
    {
        $this->variantRows[] = ['name' => '', 'priceFcfa' => ''];
    }

    public function removeVariantRow(int $index): void
    {
        unset($this->variantRows[$index]);
        $this->variantRows = array_values($this->variantRows);
    }

    public function createService(): void
    {
        $pressing = auth()->user()->currentPressing();

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'priceFcfa' => ['required', 'integer', 'min:0'],
            'variantRows.*.name' => ['nullable', 'string', 'max:100'],
            'variantRows.*.priceFcfa' => ['nullable', 'integer', 'min:0'],
        ]);

        $service = $pressing->services()->create([
            'name' => $this->name,
            'price_fcfa' => $this->priceFcfa,
            'is_active' => true,
        ]);

        foreach ($this->variantRows as $row) {
            $variantName = trim($row['name'] ?? '');

            if ($variantName === '') {
                continue;
            }

            $service->variants()->create([
                'name' => $variantName,
                'price_fcfa' => $row['priceFcfa'] !== '' ? (int) $row['priceFcfa'] : 0,
                'is_active' => true,
            ]);
        }

        $this->reset(['name', 'priceFcfa', 'variantRows', 'showCreateForm']);
    }

    public function toggleActive(Service $service): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($service->pressing_id === $pressing->id, 403);

        $service->update(['is_active' => ! $service->is_active]);
    }

    #[Layout('layouts.dashboard', ['active' => 'services', 'title' => 'Tarifs'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();

        return view('livewire.services.index', [
            'services' => $pressing->services()->withCount('variants')->orderBy('name')->get(),
        ]);
    }
}
