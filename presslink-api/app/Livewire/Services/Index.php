<?php

namespace App\Livewire\Services;

use App\Enums\Permission;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\OrderItem;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Support\ServiceIcons;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Services d'un pressing (Repassage, Nettoyage…) et prix de chaque pièce
 * (Chemise, Robe, Drap…) selon le service : le tableau « pièces × services »
 * se remplit directement, une case vide = pièce non proposée pour ce service.
 */
class Index extends Component
{
    use ShowsToasts;

    // Services (prestations)
    public bool $showPrestationForm = false;

    public ?int $editingPrestationId = null;

    public string $prestationName = '';

    public string $prestationIcon = 'autre';

    public bool $prestationIconChosen = false;

    // Pièces
    public bool $showPieceForm = false;

    public ?int $editingPieceId = null;

    public string $pieceName = '';

    public string $pieceIcon = 'autre';

    public bool $pieceIconChosen = false;

    /** @var array<int, string> prestation_id => prix saisi à la création d'une pièce */
    public array $newPiecePrices = [];

    // Variantes
    public ?int $variantFormPieceId = null;

    public string $variantName = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->may(Permission::CatalogManage, $pressing), 403);
    }

    private function pressing(): Pressing
    {
        return auth()->user()->currentPressing();
    }

    // Éléments d'un autre pressing : « introuvable » (404), jamais une exception brute.
    private function ownPrestation(int $id): Prestation
    {
        $prestation = $this->pressing()->prestations()->find($id);
        abort_if($prestation === null, 404);

        return $prestation;
    }

    private function ownPiece(int $id): Service
    {
        $piece = $this->pressing()->services()->find($id);
        abort_if($piece === null, 404);

        return $piece;
    }

    // ---------------------------------------------------------------- services

    public function newPrestation(): void
    {
        $this->resetErrorBag();
        $this->reset(['editingPrestationId', 'prestationName', 'prestationIconChosen']);
        $this->prestationIcon = 'autre';
        $this->showPrestationForm = true;
    }

    public function editPrestation(int $id): void
    {
        $prestation = $this->ownPrestation($id);
        $this->resetErrorBag();
        $this->editingPrestationId = $prestation->id;
        $this->prestationName = $prestation->name;
        $this->prestationIcon = $prestation->iconKey();
        $this->prestationIconChosen = true;
        $this->showPrestationForm = true;
    }

    public function cancelPrestation(): void
    {
        $this->reset(['showPrestationForm', 'editingPrestationId', 'prestationName', 'prestationIconChosen']);
        $this->resetErrorBag();
    }

    /** Tant que l'icône n'a pas été choisie à la main, elle suit le nom (« Retouches » → aiguille). */
    public function updatedPrestationName(): void
    {
        if (! $this->prestationIconChosen) {
            $this->prestationIcon = ServiceIcons::guessService($this->prestationName);
        }
    }

    public function choosePrestationIcon(string $key): void
    {
        abort_unless(ServiceIcons::has('service', $key), 422);
        $this->prestationIcon = $key;
        $this->prestationIconChosen = true;
    }

    public function savePrestation(): void
    {
        $pressing = $this->pressing();

        $this->prestationName = trim($this->prestationName);
        $this->validate([
            'prestationName' => ['required', 'string', 'max:60'],
            'prestationIcon' => ['required', 'string'],
        ], [
            'prestationName.required' => 'Donnez un nom au service (ex. Repassage).',
        ]);
        abort_unless(ServiceIcons::has('service', $this->prestationIcon), 422);

        $duplicate = $pressing->prestations()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($this->prestationName)])
            ->when($this->editingPrestationId, fn ($q) => $q->where('id', '!=', $this->editingPrestationId))
            ->exists();
        if ($duplicate) {
            $this->addError('prestationName', 'Vous avez déjà un service de ce nom.');

            return;
        }

        if ($this->editingPrestationId !== null) {
            $this->ownPrestation($this->editingPrestationId)->update(['name' => $this->prestationName, 'icon' => $this->prestationIcon]);
            $this->toast("Service « {$this->prestationName} » modifié.");
        } else {
            $pressing->prestations()->create([
                'name' => $this->prestationName,
                'icon' => $this->prestationIcon,
                'position' => (int) $pressing->prestations()->max('position') + 1,
                'is_active' => true,
            ]);
            $this->toast("Service « {$this->prestationName} » ajouté : renseignez maintenant les prix de vos pièces.");
        }

        $this->cancelPrestation();
    }

    public function togglePrestation(int $id): void
    {
        $prestation = $this->ownPrestation($id);
        $prestation->update(['is_active' => ! $prestation->is_active]);

        $this->toast($prestation->is_active
            ? "« {$prestation->name} » est de nouveau proposé."
            : "« {$prestation->name} » est désactivé : il n'apparaît plus dans les nouvelles commandes.", $prestation->is_active ? 'success' : 'warning');
    }

    /** Supprime un service jamais utilisé ; sinon le désactive (l'historique des commandes reste exact). */
    public function deletePrestation(int $id): void
    {
        $prestation = $this->ownPrestation($id);

        if ($prestation->isUsed()) {
            $prestation->update(['is_active' => false]);
            $this->toast("« {$prestation->name} » figure dans des commandes : il est désactivé plutôt que supprimé.", 'warning');

            return;
        }

        $prestation->delete();
        $this->toast("Service « {$prestation->name} » supprimé.");
    }

    public function movePrestation(int $id, string $direction): void
    {
        $list = $this->pressing()->prestations()->get()->values();
        $index = $list->search(fn (Prestation $p) => $p->id === $id);
        $swap = $direction === 'left' ? $index - 1 : $index + 1;

        if ($index === false || $swap < 0 || $swap >= $list->count()) {
            return;
        }

        $moved = $list->splice($index, 1)->first();
        $list->splice($swap, 0, [$moved]);
        $list->each(fn (Prestation $p, int $i) => $p->update(['position' => $i + 1]));
    }

    // ----------------------------------------------------------------- pièces

    public function newPiece(): void
    {
        $this->resetErrorBag();
        $this->reset(['editingPieceId', 'pieceName', 'pieceIconChosen', 'newPiecePrices']);
        $this->pieceIcon = 'autre';
        $this->showPieceForm = true;
    }

    public function editPiece(int $id): void
    {
        $piece = $this->ownPiece($id);
        $this->resetErrorBag();
        $this->editingPieceId = $piece->id;
        $this->pieceName = $piece->name;
        $this->pieceIcon = $piece->iconKey();
        $this->pieceIconChosen = true;
        $this->newPiecePrices = [];
        $this->showPieceForm = true;
    }

    public function cancelPiece(): void
    {
        $this->reset(['showPieceForm', 'editingPieceId', 'pieceName', 'pieceIconChosen', 'newPiecePrices']);
        $this->resetErrorBag();
    }

    public function updatedPieceName(): void
    {
        if (! $this->pieceIconChosen) {
            $this->pieceIcon = ServiceIcons::guessPiece($this->pieceName);
        }
    }

    public function choosePieceIcon(string $key): void
    {
        abort_unless(ServiceIcons::has('piece', $key), 422);
        $this->pieceIcon = $key;
        $this->pieceIconChosen = true;
    }

    public function savePiece(): void
    {
        $pressing = $this->pressing();
        $this->errorMessage = null;

        $this->pieceName = trim($this->pieceName);
        $this->validate([
            'pieceName' => ['required', 'string', 'max:100'],
            'newPiecePrices.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'pieceName.required' => 'Donnez un nom à la pièce (ex. Chemise).',
            'newPiecePrices.*.integer' => 'Un prix doit être un nombre entier de francs.',
        ]);
        abort_unless(ServiceIcons::has('piece', $this->pieceIcon), 422);

        if ($this->editingPieceId !== null) {
            $this->ownPiece($this->editingPieceId)->update(['name' => $this->pieceName, 'icon' => $this->pieceIcon]);
            $this->toast("Pièce « {$this->pieceName} » modifiée.");
            $this->cancelPiece();

            return;
        }

        $piece = $pressing->services()->create(['name' => $this->pieceName, 'icon' => $this->pieceIcon, 'price_fcfa' => 0, 'is_active' => true]);

        $validPrestationIds = $pressing->prestations()->pluck('id')->all();
        foreach ($this->newPiecePrices as $prestationId => $amount) {
            if ($amount !== null && $amount !== '' && in_array((int) $prestationId, $validPrestationIds, true)) {
                $piece->setPrice((int) $prestationId, (int) $amount);
            }
        }

        $this->toast("Pièce « {$piece->name} » ajoutée.");
        $this->cancelPiece();
    }

    public function togglePiece(int $id): void
    {
        $piece = $this->ownPiece($id);
        $piece->update(['is_active' => ! $piece->is_active]);

        $this->toast($piece->is_active
            ? "« {$piece->name} » est de nouveau proposée."
            : "« {$piece->name} » est désactivée : elle n'apparaît plus dans les nouvelles commandes.", $piece->is_active ? 'success' : 'warning');
    }

    public function deletePiece(int $id): void
    {
        $piece = $this->ownPiece($id);

        if (OrderItem::where('service_id', $piece->id)->exists()) {
            $piece->update(['is_active' => false]);
            $this->toast("« {$piece->name} » figure dans des commandes : elle est désactivée plutôt que supprimée.", 'warning');

            return;
        }

        $piece->delete();
        $this->toast("Pièce « {$piece->name} » supprimée.");
    }

    // ------------------------------------------------------------------ prix

    /** Enregistre le prix d'une pièce (ou variante) pour un service ; vide = non proposée. */
    public function setPrice(int $pieceId, ?int $variantId, int $prestationId, string $value): void
    {
        $piece = $this->ownPiece($pieceId);
        $prestation = $this->ownPrestation($prestationId);
        $variant = $variantId !== null ? $piece->variants()->find($variantId) : null;
        abort_if($variantId !== null && $variant === null, 404);

        $value = trim(str_replace([' ', "\u{202F}", "\u{00A0}"], '', $value));

        if ($value === '') {
            $piece->setPrice($prestation, null, $variant);

            return;
        }

        if (! ctype_digit($value) || (int) $value > 10_000_000) {
            $this->toast('Saisissez un prix en francs, sans décimale (ex. 1500).', 'error');

            return;
        }

        $piece->setPrice($prestation, (int) $value, $variant);
    }

    // -------------------------------------------------------------- variantes

    public function newVariant(int $pieceId): void
    {
        $this->ownPiece($pieceId);
        $this->variantFormPieceId = $pieceId;
        $this->variantName = '';
        $this->resetErrorBag('variantName');
    }

    public function cancelVariant(): void
    {
        $this->reset(['variantFormPieceId', 'variantName']);
        $this->resetErrorBag('variantName');
    }

    public function saveVariant(): void
    {
        abort_if($this->variantFormPieceId === null, 422);
        $piece = $this->ownPiece($this->variantFormPieceId);

        $this->variantName = trim($this->variantName);
        $this->validate(['variantName' => ['required', 'string', 'max:100']], ['variantName.required' => 'Donnez un nom à la variante (ex. Manche longue).']);

        if ($piece->variants()->whereRaw('LOWER(name) = ?', [mb_strtolower($this->variantName)])->exists()) {
            $this->addError('variantName', 'Cette variante existe déjà.');

            return;
        }

        $piece->variants()->create(['name' => $this->variantName, 'price_fcfa' => 0, 'is_active' => true]);
        $this->toast("Variante « {$this->variantName} » ajoutée : renseignez ses prix dans le tableau.");
        $this->cancelVariant();
    }

    public function deleteVariant(int $variantId): void
    {
        $variant = ServiceVariant::whereHas('service', fn ($q) => $q->where('pressing_id', $this->pressing()->id))->find($variantId);
        abort_if($variant === null, 404);

        if (OrderItem::where('service_variant_id', $variant->id)->exists()) {
            $variant->update(['is_active' => false]);
            $this->toast("« {$variant->name} » figure dans des commandes : elle est désactivée plutôt que supprimée.", 'warning');

            return;
        }

        $variant->delete();
        $this->toast("Variante « {$variant->name} » supprimée.");
    }

    #[Layout('layouts.dashboard', ['active' => 'services', 'title' => 'Services'])]
    public function render()
    {
        $pressing = $this->pressing();

        $prestations = $pressing->prestations()->withCount('prices')->get();
        $pieces = $pressing->services()
            ->with(['variants' => fn ($q) => $q->orderBy('name'), 'prices'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        // prix[pièce][variante|0][service] pour remplir le tableau sans requête par case
        $prices = [];
        foreach ($pieces as $piece) {
            foreach ($piece->prices as $price) {
                $prices[$piece->id][$price->service_variant_id ?? 0][$price->prestation_id] = $price->price_fcfa;
            }
        }

        return view('livewire.services.index', [
            'prestations' => $prestations,
            'pieces' => $pieces,
            'prices' => $prices,
            'serviceIcons' => ServiceIcons::options('service'),
            'pieceIcons' => ServiceIcons::options('piece'),
        ]);
    }
}
