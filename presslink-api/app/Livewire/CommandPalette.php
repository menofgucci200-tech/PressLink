<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Palette de commandes (Ctrl/⌘ + K) : aller directement à une commande, un
 * client ou un pressing, ou lancer une action courante (nouvelle commande,
 * commandes prêtes…), sans passer par les menus. Toujours limitée à ce que
 * l'utilisateur a le droit de voir : son pressing courant, ou toute la
 * plateforme pour le Super Admin dans la console.
 */
class CommandPalette extends Component
{
    public string $query = '';

    /** Console Super Admin (sinon : espace pressing). */
    public bool $admin = false;

    public function mount(bool $admin = false): void
    {
        $this->admin = $admin && auth()->user()->is_super_admin;
    }

    /**
     * @return list<array{label: string, items: list<array{title: string, subtitle: ?string, url: ?string, icon: string, action?: string, hint?: string}>}>
     */
    private function groups(): array
    {
        $term = trim($this->query);
        $groups = [];

        if (mb_strlen($term) >= 2) {
            $groups = $this->admin ? $this->adminSearch($term) : $this->pressingSearch($term);
        }

        $commands = array_values(array_filter(
            $this->admin ? $this->adminCommands() : $this->pressingCommands(),
            fn (array $command) => $term === '' || Str::contains(Str::ascii(mb_strtolower($command['title'])), Str::ascii(mb_strtolower($term))),
        ));

        if ($commands !== []) {
            $groups[] = ['label' => 'Actions', 'items' => $commands];
        }

        return array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
    }

    /** @return list<array{label: string, items: list<array<string, mixed>>}> */
    private function pressingSearch(string $term): array
    {
        $pressing = auth()->user()->currentPressing();
        if ($pressing === null) {
            return [];
        }

        $orders = $pressing->filteredOrders(['search' => $term])->latest()->take(5)->get()
            ->map(fn (Order $order) => [
                'title' => $order->order_number.' · '.$order->customer->fullName(),
                'subtitle' => $order->status->label().' · '.number_format($order->total_fcfa, 0, ',', ' ').' F'
                    .($order->status !== OrderStatus::Annulee && $order->balance_due_fcfa > 0 ? ' · reste '.number_format($order->balance_due_fcfa, 0, ',', ' ').' F' : ''),
                'url' => route('orders.show', $order),
                'icon' => 'order',
            ])->all();

        $clients = $pressing->customers()
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"))
            ->orderBy('last_name')
            ->take(5)
            ->get()
            ->map(fn (Customer $customer) => [
                'title' => $customer->fullName(),
                'subtitle' => $customer->phone,
                'url' => route('clients.show', $customer),
                'icon' => 'client',
            ])->all();

        return [
            ['label' => 'Commandes', 'items' => $orders],
            ['label' => 'Clients', 'items' => $clients],
        ];
    }

    /** @return list<array{label: string, items: list<array<string, mixed>>}> */
    private function adminSearch(string $term): array
    {
        $pressings = Pressing::query()
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%"))
            ->orderBy('name')
            ->take(5)
            ->get()
            ->map(fn (Pressing $pressing) => [
                'title' => $pressing->name,
                'subtitle' => collect([$pressing->code, $pressing->city, $pressing->status->label()])->filter()->implode(' · '),
                'url' => route('admin.pressings.show', $pressing),
                'icon' => 'pressing',
            ])->all();

        $orders = Order::query()->with(['customer', 'pressing:id,name'])
            ->where('order_number', 'like', "%{$term}%")
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Order $order) => [
                'title' => $order->order_number.' · '.$order->customer->fullName(),
                'subtitle' => $order->pressing->name.' · '.$order->status->label(),
                'url' => route('admin.orders.index', ['commande' => $order->id, 'search' => $order->order_number]),
                'icon' => 'order',
            ])->all();

        $clients = Customer::query()
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"))
            ->orderBy('last_name')
            ->take(5)
            ->get()
            ->map(fn (Customer $customer) => [
                'title' => $customer->fullName() ?: $customer->phone,
                'subtitle' => $customer->phone,
                'url' => route('admin.clients.index', ['client' => $customer->id]),
                'icon' => 'client',
            ])->all();

        return [
            ['label' => 'Pressings', 'items' => $pressings],
            ['label' => 'Commandes', 'items' => $orders],
            ['label' => 'Clients', 'items' => $clients],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function pressingCommands(): array
    {
        $user = auth()->user();
        $pressing = $user->currentPressing();
        $isAdmin = $pressing !== null && $user->isAdminOf($pressing);

        return array_values(array_filter([
            ['title' => 'Nouvelle commande', 'subtitle' => 'Enregistrer un dépôt', 'url' => route('orders.create'), 'icon' => 'plus', 'hint' => 'N'],
            ['title' => 'Tableau de bord', 'subtitle' => null, 'url' => route('dashboard'), 'icon' => 'home', 'hint' => 'G D'],
            ['title' => 'Toutes les commandes', 'subtitle' => null, 'url' => route('orders.index'), 'icon' => 'order', 'hint' => 'G C'],
            ['title' => 'Commandes prêtes à retirer', 'subtitle' => null, 'url' => route('orders.index', ['status' => OrderStatus::Prete->value]), 'icon' => 'check', 'hint' => 'G P'],
            ['title' => 'Commandes non soldées', 'subtitle' => 'Acompte ou rien payé', 'url' => route('orders.index', ['paiement' => Pressing::UNSETTLED_FILTER]), 'icon' => 'money'],
            ['title' => 'Clients', 'subtitle' => null, 'url' => route('clients.index'), 'icon' => 'client', 'hint' => 'G L'],
            ['title' => 'Signalements', 'subtitle' => null, 'url' => route('issues.index'), 'icon' => 'flag', 'hint' => 'G S'],
            $isAdmin ? ['title' => 'Tarifs', 'subtitle' => null, 'url' => route('services.index'), 'icon' => 'tag'] : null,
            $isAdmin ? ['title' => 'Équipe', 'subtitle' => null, 'url' => route('team.index'), 'icon' => 'team'] : null,
            $isAdmin ? ['title' => 'Paramètres du pressing', 'subtitle' => null, 'url' => route('pressing.settings'), 'icon' => 'settings'] : null,
            ['title' => 'Mon profil', 'subtitle' => null, 'url' => route('account.settings'), 'icon' => 'user'],
            ['title' => 'Mode jour / nuit', 'subtitle' => null, 'url' => null, 'icon' => 'theme', 'action' => 'theme'],
            ['title' => 'Raccourcis clavier', 'subtitle' => null, 'url' => null, 'icon' => 'keyboard', 'action' => 'shortcuts', 'hint' => '?'],
        ]));
    }

    /** @return list<array<string, mixed>> */
    private function adminCommands(): array
    {
        return [
            ['title' => 'Vue globale', 'subtitle' => null, 'url' => route('admin.dashboard'), 'icon' => 'home', 'hint' => 'G D'],
            ['title' => 'Nouveau pressing', 'subtitle' => null, 'url' => route('admin.pressings.create'), 'icon' => 'plus', 'hint' => 'N'],
            ['title' => 'Pressings', 'subtitle' => null, 'url' => route('admin.pressings.index'), 'icon' => 'pressing', 'hint' => 'G P'],
            ['title' => 'Commandes', 'subtitle' => null, 'url' => route('admin.orders.index'), 'icon' => 'order', 'hint' => 'G C'],
            ['title' => 'Commandes en retard', 'subtitle' => null, 'url' => route('admin.orders.index', ['retard' => 1]), 'icon' => 'clock'],
            ['title' => 'Signalements ouverts', 'subtitle' => null, 'url' => route('admin.orders.index', ['signalements' => 1]), 'icon' => 'flag'],
            ['title' => 'Clients', 'subtitle' => null, 'url' => route('admin.clients.index'), 'icon' => 'client', 'hint' => 'G L'],
            ['title' => 'Administrateurs', 'subtitle' => null, 'url' => route('admin.administrators.index'), 'icon' => 'team'],
            ['title' => 'Liste des utilisateurs', 'subtitle' => null, 'url' => route('admin.users.index'), 'icon' => 'user'],
            ['title' => 'Mon profil', 'subtitle' => null, 'url' => route('account.settings'), 'icon' => 'user'],
            ['title' => 'Mode jour / nuit', 'subtitle' => null, 'url' => null, 'icon' => 'theme', 'action' => 'theme'],
            ['title' => 'Raccourcis clavier', 'subtitle' => null, 'url' => null, 'icon' => 'keyboard', 'action' => 'shortcuts', 'hint' => '?'],
        ];
    }

    public function render()
    {
        return view('livewire.command-palette', ['groups' => $this->groups()]);
    }
}
