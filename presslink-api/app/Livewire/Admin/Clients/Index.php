<?php

namespace App\Livewire\Admin\Clients;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vue Clients plateforme — Super Admin, pour retrouver un client sans
 * connaître son pressing et consulter son activité (pressings rejoints,
 * commandes, montant dépensé) sans quitter la console.
 */
class Index extends Component
{
    use WithPagination;

    /** Un client est « actif » s'il a passé commande dans ce nombre de jours. */
    public const ACTIVE_WITHIN_DAYS = 30;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'pressing', except: '')]
    public string $pressingId = '';

    #[Url(as: 'activite', except: '')]
    public string $activity = '';

    #[Url(as: 'tri', except: 'recent')]
    public string $sort = 'recent';

    /** Client affiché dans le panneau de détail. */
    #[Url(as: 'client', except: null)]
    public ?int $selectedCustomerId = null;

    /** Formulaire : 'create' (nouveau client), 'edit' (client sélectionné) ou null. */
    public ?string $formMode = null;

    /** @var array{first_name: string, last_name: string, phone: string, email: string, pressing_id: string} */
    public array $form = ['first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '', 'pressing_id' => ''];

    public string $attachPressingId = '';

    /** Mot de passe temporaire affiché une seule fois après réinitialisation. */
    public ?string $generatedPassword = null;

    public ?string $flash = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'pressingId', 'activity', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'pressingId', 'activity', 'sort']);
        $this->resetPage();
    }

    public function showCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->reset(['formMode', 'generatedPassword', 'attachPressingId', 'flash']);
        $this->resetErrorBag();
    }

    public function closeCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->reset(['formMode', 'generatedPassword', 'attachPressingId']);
    }

    public function openCreateForm(): void
    {
        $this->selectedCustomerId = null;
        $this->form = ['first_name' => '', 'last_name' => '', 'phone' => '+2250', 'email' => '', 'pressing_id' => $this->pressingId];
        $this->resetErrorBag();
        $this->formMode = 'create';
    }

    public function editCustomer(): void
    {
        $customer = Customer::findOrFail($this->selectedCustomerId);
        $this->form = [
            'first_name' => (string) $customer->first_name,
            'last_name' => (string) $customer->last_name,
            'phone' => $customer->phone,
            'email' => (string) $customer->email,
            'pressing_id' => '',
        ];
        $this->resetErrorBag();
        $this->formMode = 'edit';
    }

    public function cancelForm(): void
    {
        $this->formMode = null;
        $this->resetErrorBag();
    }

    public function saveCustomer(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $editing = $this->formMode === 'edit' ? Customer::findOrFail($this->selectedCustomerId) : null;
        $this->form['phone'] = preg_replace('/\s+/', '', $this->form['phone']);

        $this->validate([
            'form.first_name' => ['required', 'string', 'max:100'],
            'form.last_name' => ['required', 'string', 'max:100'],
            'form.phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/', Rule::unique('customers', 'phone')->ignore($editing?->id)],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.pressing_id' => ['nullable', 'integer', 'exists:pressings,id'],
        ], [
            'form.phone.regex' => 'Le téléphone doit être au format +2250XXXXXXXXX.',
            'form.phone.unique' => 'Un client existe déjà avec ce numéro.',
        ], [
            'form.first_name' => 'prénom', 'form.last_name' => 'nom', 'form.phone' => 'téléphone',
            'form.email' => 'email', 'form.pressing_id' => 'pressing',
        ]);

        $data = [
            'first_name' => trim($this->form['first_name']),
            'last_name' => trim($this->form['last_name']),
            'phone' => $this->form['phone'],
            'email' => trim($this->form['email']) ?: null,
        ];

        if ($editing) {
            $editing->update($data);
            $this->flash = 'Client mis à jour.';
        } else {
            $customer = DB::transaction(function () use ($data) {
                $customer = Customer::create([
                    ...$data,
                    'password' => Customer::DEFAULT_WALK_IN_PASSWORD,
                    'phone_verified_at' => now(),
                ]);

                if ($this->form['pressing_id'] !== '') {
                    $customer->pressings()->attach((int) $this->form['pressing_id'], ['joined_at' => now()]);
                }

                return $customer;
            });
            $this->selectedCustomerId = $customer->id;
            $this->flash = 'Client créé. Mot de passe par défaut : '.Customer::DEFAULT_WALK_IN_PASSWORD.' (à changer depuis l\'application).';
        }

        $this->formMode = null;
    }

    public function attachPressing(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $this->validate(['attachPressingId' => ['required', 'integer', 'exists:pressings,id']], [], ['attachPressingId' => 'pressing']);

        Customer::findOrFail($this->selectedCustomerId)->pressings()
            ->syncWithoutDetaching([(int) $this->attachPressingId => ['joined_at' => now()]]);

        $this->attachPressingId = '';
    }

    public function detachPressing(int $pressingId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        Customer::findOrFail($this->selectedCustomerId)->pressings()->detach($pressingId);
    }

    public function resetPassword(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $customer = Customer::findOrFail($this->selectedCustomerId);
        $password = Str::password(8, symbols: false);
        $customer->update(['password' => $password]);
        // Les sessions ouvertes dans l'application sont fermées.
        $customer->tokens()->delete();

        $this->generatedPassword = $password;
    }

    /**
     * Désactive le compte (connexion à l'application refusée, sessions
     * fermées, notifications coupées) ou le réactive. Ses commandes et
     * rattachements restent intacts.
     */
    public function toggleActive(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $customer = Customer::findOrFail($this->selectedCustomerId);

        if ($customer->is_active) {
            DB::transaction(function () use ($customer) {
                $customer->update(['is_active' => false, 'onesignal_player_id' => null, 'fcm_token' => null]);
                $customer->tokens()->delete();
            });
            $this->flash = 'Compte désactivé : le client ne peut plus se connecter à l\'application.';
        } else {
            $customer->update(['is_active' => true]);
            $this->flash = 'Compte réactivé : le client peut de nouveau se connecter.';
        }
    }

    /**
     * Suppression définitive du compte client : ses commandes et
     * signalements partent en cascade, ses sessions et notifications sont
     * effacées.
     */
    public function deleteCustomer(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $customer = Customer::findOrFail($this->selectedCustomerId);
        $name = $customer->fullName() ?: $customer->phone;

        DB::transaction(function () use ($customer) {
            $customer->tokens()->delete();
            $customer->notifications()->delete();
            $customer->delete();
        });

        $this->closeCustomer();
        $this->flash = "Le client « {$name} » a été supprimé.";
    }

    /** @return array<string, string> */
    public function filters(): array
    {
        return [
            'search' => $this->search,
            'pressing' => $this->pressingId,
            'activity' => $this->activity,
            'sort' => $this->sort,
        ];
    }

    /**
     * @param  array<string, ?string>  $filters
     * @return Builder<Customer>
     */
    public static function filteredQuery(array $filters): Builder
    {
        $activeSince = now()->subDays(self::ACTIVE_WITHIN_DAYS);

        $query = Customer::query()
            ->with('pressings:id,name')
            ->withCount(['pressings', 'orders'])
            ->withSum(['orders as total_spent' => fn ($q) => $q->where('status', '!=', OrderStatus::Annulee->value)], 'total_fcfa')
            ->withMax('orders as last_order_at', 'created_at');

        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhereRaw(self::fullNameSql($q->getConnection()->getDriverName()).' like ?', ["%{$term}%"])
                ->orWhere('phone', 'like', '%'.preg_replace('/\s+/', '', $term).'%')
                ->orWhere('email', 'like', "%{$term}%"));
        }

        if ($pressingId = $filters['pressing'] ?? '') {
            $query->whereHas('pressings', fn ($q) => $q->where('pressings.id', $pressingId));
        }

        match ($filters['activity'] ?? '') {
            'active' => $query->whereHas('orders', fn ($q) => $q->where('created_at', '>=', $activeSince)),
            'inactive' => $query->whereHas('orders')->whereDoesntHave('orders', fn ($q) => $q->where('created_at', '>=', $activeSince)),
            'never' => $query->whereDoesntHave('orders'),
            'disabled' => $query->where('is_active', false),
            default => null,
        };

        match ($filters['sort'] ?? 'recent') {
            'name' => $query->orderBy('first_name')->orderBy('last_name'),
            'orders' => $query->orderByDesc('orders_count'),
            'spent' => $query->orderByDesc('total_spent'),
            'last_order' => $query->orderByRaw('last_order_at IS NULL')->orderByDesc('last_order_at'),
            default => $query->latest()->orderByDesc('id'),
        };

        return $query;
    }

    /** « Prénom Nom » en SQL, pour chercher un nom complet (MySQL en production, SQLite en test). */
    private static function fullNameSql(string $driver): string
    {
        return $driver === 'sqlite'
            ? "(first_name || ' ' || last_name)"
            : "CONCAT(first_name, ' ', last_name)";
    }

    #[Layout('layouts.admin', ['active' => 'clients', 'title' => 'Clients'])]
    public function render()
    {
        $activeSince = now()->subDays(self::ACTIVE_WITHIN_DAYS);

        return view('livewire.admin.clients.index', [
            'customers' => self::filteredQuery($this->filters())->paginate(20),
            'summary' => [
                'total' => Customer::count(),
                'new' => Customer::where('created_at', '>=', $activeSince)->count(),
                'active' => Customer::whereHas('orders', fn ($q) => $q->where('created_at', '>=', $activeSince))->count(),
                'never' => Customer::whereDoesntHave('orders')->count(),
                'disabled' => Customer::where('is_active', false)->count(),
            ],
            'pressings' => Pressing::orderBy('name')->get(['id', 'name']),
            'selectedCustomer' => $this->selectedCustomerId
                ? Customer::withCount('orders')
                    ->withSum(['orders as total_spent' => fn ($q) => $q->where('status', '!=', OrderStatus::Annulee->value)], 'total_fcfa')
                    ->with([
                        'pressings' => fn ($q) => $q->orderByPivot('joined_at', 'desc'),
                        'orders' => fn ($q) => $q->with('pressing:id,name')->latest()->take(10),
                    ])
                    ->find($this->selectedCustomerId)
                : null,
            'hasFilters' => collect($this->filters())->except('sort')->filter()->isNotEmpty() || $this->sort !== 'recent',
        ]);
    }
}
