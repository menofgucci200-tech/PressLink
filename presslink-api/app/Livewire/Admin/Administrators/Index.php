<?php

namespace App\Livewire\Admin\Administrators;

use App\Enums\PressingRole;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Administrateurs de pressing — Super Admin. Un pressing nouvellement créé
 * n'a pas d'administrateur (cf. Pressings\Create) : c'est ici qu'on lui
 * en assigne un.
 */
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showCreateForm = false;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $pressingId = '';

    public ?string $generatedPassword = null;

    public ?User $createdAdmin = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $preselected = request()->query('pressing');

        if ($preselected !== null) {
            $this->pressingId = (string) $preselected;
            $this->showCreateForm = true;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectPressingForNewAdmin(int $pressingId): void
    {
        $this->pressingId = (string) $pressingId;
        $this->showCreateForm = true;
    }

    public function createAdministrator(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/', 'unique:users,phone'],
            'pressingId' => ['required', 'exists:pressings,id'],
        ]);

        $pressing = Pressing::findOrFail($this->pressingId);
        $password = Str::password(10);

        $admin = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => $password,
        ]);

        $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $this->createdAdmin = $admin;
        $this->generatedPassword = $password;
        $this->reset(['name', 'email', 'phone', 'pressingId', 'showCreateForm']);
    }

    #[Layout('layouts.admin', ['active' => 'administrators', 'title' => 'Administrateurs'])]
    public function render()
    {
        $query = User::whereHas('pressings', fn ($q) => $q->where('pressing_users.role', PressingRole::Admin->value)
            ->where('pressing_users.is_active', true))
            ->with(['pressings' => fn ($q) => $q->where('pressing_users.role', PressingRole::Admin->value)
                ->where('pressing_users.is_active', true)]);

        if ($this->search !== '') {
            $term = $this->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"));
        }

        return view('livewire.admin.administrators.index', [
            'administrators' => $query->orderBy('name')->paginate(20),
            'pressings' => Pressing::orderBy('name')->get(),
            'pressingsWithoutAdmin' => Pressing::whereDoesntHave('staff', fn ($q) => $q->where('pressing_users.role', PressingRole::Admin->value)
                ->where('pressing_users.is_active', true))->orderBy('name')->get(),
        ]);
    }
}
