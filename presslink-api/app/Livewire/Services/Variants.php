<?php

namespace App\Livewire\Services;

use App\Models\Service;
use App\Models\ServiceVariant;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Déclinaisons d'un service avec leur propre tarif — ex. "Chemise" manche
 * courte vs manche longue, chacune à son prix. Utilisées lors de l'ajout
 * d'un article à une commande (cf. Orders\Create).
 */
class Variants extends Component
{
    public Service $service;

    public bool $showCreateForm = false;

    public string $name = '';

    public string $priceFcfa = '';

    public function mount(Service $service): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->isAdminOf($pressing), 403);
        abort_unless($service->pressing_id === $pressing->id, 403);

        $this->service = $service;
    }

    public function createVariant(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'priceFcfa' => ['required', 'integer', 'min:0'],
        ]);

        $this->service->variants()->create([
            'name' => $this->name,
            'price_fcfa' => $this->priceFcfa,
            'is_active' => true,
        ]);

        $this->reset(['name', 'priceFcfa', 'showCreateForm']);
    }

    public function toggleActive(ServiceVariant $variant): void
    {
        abort_unless($variant->service_id === $this->service->id, 403);

        $variant->update(['is_active' => ! $variant->is_active]);
    }

    #[Layout('layouts.dashboard', ['active' => 'services', 'title' => 'Variantes'])]
    public function render()
    {
        return view('livewire.services.variants', [
            'variants' => $this->service->variants()->orderBy('name')->get(),
        ]);
    }
}
