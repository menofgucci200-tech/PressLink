<?php

namespace App\Livewire\Admin\Users;

use App\Actions\Auth\ResetPasswordToDefault;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste des utilisateurs — Super Admin : tous les comptes de PressLink
 * (équipes des pressings, clients de l'application, super
 * administrateurs), avec la réinitialisation du mot de passe à la valeur
 * par défaut {@see ResetPasswordToDefault::DEFAULT_PASSWORD}.
 */
class Index extends Component
{
    use ShowsToasts, WithPagination;

    public const TYPES = ['staff' => 'Équipes des pressings', 'customers' => 'Clients', 'admins' => 'Super administrateurs'];

    #[Url(as: 'type', except: 'staff')]
    public string $type = 'staff';

    #[Url(except: '')]
    public string $search = '';

    /** Uniquement les comptes dont le mot de passe reste à changer. */
    #[Url(as: 'a-changer', except: false)]
    public bool $mustChangeOnly = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        if (! array_key_exists($this->type, self::TYPES)) {
            $this->type = 'staff';
        }
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['type', 'search', 'mustChangeOnly'], true)) {
            $this->resetPage();
        }
    }

    public function resetUserPassword(int $userId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $user = User::findOrFail($userId);

        if ($user->is($this->currentUser())) {
            $this->toast('Pour votre propre compte, changez votre mot de passe depuis « Mon compte ».', 'warning');

            return;
        }

        (new ResetPasswordToDefault)->handle($user);

        $this->toast("Le mot de passe de {$user->name} est maintenant ".ResetPasswordToDefault::DEFAULT_PASSWORD.'. Il devra le changer depuis « Mon compte ».');
    }

    public function resetCustomerPassword(int $customerId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $customer = Customer::findOrFail($customerId);
        (new ResetPasswordToDefault)->handle($customer);

        $name = $customer->fullName() ?: $customer->phone;
        $this->toast("Le mot de passe de {$name} est maintenant ".ResetPasswordToDefault::DEFAULT_PASSWORD.'. Le client devra le changer depuis son profil dans l\'application.');
    }

    private function currentUser(): User
    {
        return auth()->user();
    }

    private function accounts(): LengthAwarePaginator
    {
        $term = trim($this->search);

        if ($this->type === 'customers') {
            return Customer::query()
                ->with('pressings:id,name')
                ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', '%'.preg_replace('/\s+/', '', $term).'%')
                    ->orWhere('email', 'like', "%{$term}%")))
                ->when($this->mustChangeOnly, fn ($q) => $q->where('must_change_password', true))
                ->orderBy('first_name')->orderBy('last_name')
                ->paginate(20);
        }

        return User::query()
            ->where('is_super_admin', $this->type === 'admins')
            ->with('pressings:id,name')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('login', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', '%'.preg_replace('/\s+/', '', $term).'%')))
            ->when($this->mustChangeOnly, fn ($q) => $q->where('must_change_password', true))
            ->orderBy('name')
            ->paginate(20);
    }

    #[Layout('layouts.admin', ['active' => 'users', 'title' => 'Liste des utilisateurs'])]
    public function render()
    {
        return view('livewire.admin.users.index', [
            'accounts' => $this->accounts(),
            'counts' => [
                'staff' => User::where('is_super_admin', false)->count(),
                'customers' => Customer::count(),
                'admins' => User::where('is_super_admin', true)->count(),
            ],
            'mustChangeCount' => $this->type === 'customers'
                ? Customer::where('must_change_password', true)->count()
                : User::where('is_super_admin', $this->type === 'admins')->where('must_change_password', true)->count(),
            'defaultPassword' => ResetPasswordToDefault::DEFAULT_PASSWORD,
        ]);
    }
}
