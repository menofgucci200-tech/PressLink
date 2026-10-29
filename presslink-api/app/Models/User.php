<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\PressingRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Membre du staff d'un ou plusieurs pressings (employé ou admin).
 * Distinct de Customer (client final, authentifié par téléphone).
 */
#[Fillable(['name', 'email', 'phone', 'login', 'avatar_path', 'password', 'must_change_password', 'is_super_admin', 'group_report_opt_in'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'group_report_opt_in' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /** URL publique de la photo de profil, null tant qu'aucune n'est envoyée. */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
        );
    }

    /** Initiales affichées à la place de la photo (« Koffi Gérant » → « KG »). */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn () => collect(explode(' ', (string) $this->name))
                ->filter()
                ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
                ->take(2)
                ->implode(''),
        );
    }

    /** @return BelongsToMany<Pressing, $this> */
    public function pressings(): BelongsToMany
    {
        return $this->belongsToMany(Pressing::class, 'pressing_users')
            ->using(PressingUser::class)
            ->withPivot(['role', 'role_id', 'is_active'])
            ->withTimestamps();
    }

    /** @var array<int, Role|null> rôle par pressing, pour la durée de la requête */
    private array $rolesByPressing = [];

    /**
     * Rôle détaillé du membre dans ce pressing (Propriétaire, Gérant…). Un
     * membre rattaché sans rôle détaillé prend celui de son ancien rôle :
     * administrateur → Propriétaire, employé → Employé.
     */
    public function roleIn(Pressing $pressing): ?Role
    {
        if (array_key_exists($pressing->id, $this->rolesByPressing)) {
            return $this->rolesByPressing[$pressing->id];
        }

        $pivot = PressingUser::query()
            ->where('pressing_id', $pressing->id)
            ->where('user_id', $this->id)
            ->where('is_active', true)
            ->first();

        $role = null;
        if ($pivot !== null) {
            $role = $pivot->role_id !== null ? Role::find($pivot->role_id) : null;
            $role ??= $pressing->defaultRole($pivot->role === PressingRole::Admin ? Role::OWNER : Role::EMPLOYEE);
        }

        return $this->rolesByPressing[$pressing->id] = $role;
    }

    /** @var array<int, Subscription|null> abonnement par pressing, pour la durée de la requête */
    private array $subscriptionsByPressing = [];

    /**
     * Le membre a-t-il cet accès dans ce pressing (par défaut, le pressing
     * actif) ? Le propriétaire a tous les accès, dans la limite de ce que
     * comprend la formule du pressing (App\Enums\Feature).
     */
    public function may(Permission $permission, ?Pressing $pressing = null): bool
    {
        $pressing ??= $this->currentPressing();

        if ($pressing === null) {
            return false;
        }

        $feature = Feature::forPermission($permission);
        if ($feature !== null && ! $this->pressingHasFeature($pressing, $feature)) {
            return false;
        }

        return $this->roleIn($pressing)?->allows($permission) ?? false;
    }

    /**
     * Accès que le rôle donnerait mais que la formule du pressing n'inclut
     * pas : la fonction à proposer (page Abonnement), sinon null.
     */
    public function featureLockedFor(Permission $permission, ?Pressing $pressing = null): ?Feature
    {
        $pressing ??= $this->currentPressing();
        $feature = Feature::forPermission($permission);

        if ($pressing === null || $feature === null || $this->pressingHasFeature($pressing, $feature)) {
            return null;
        }

        return ($this->roleIn($pressing)?->allows($permission) ?? false) ? $feature : null;
    }

    /** La formule (ou une option) du pressing comprend-elle cette fonction ? */
    public function pressingHasFeature(Pressing $pressing, Feature $feature): bool
    {
        if (! array_key_exists($pressing->id, $this->subscriptionsByPressing)) {
            $this->subscriptionsByPressing[$pressing->id] = $pressing->subscription;
        }

        return $this->subscriptionsByPressing[$pressing->id]?->hasFeature($feature) ?? true;
    }

    /** « Propriétaire », « Gérant », « Caissière »… */
    public function roleLabelIn(Pressing $pressing): string
    {
        return $this->roleIn($pressing)?->name ?? 'Membre';
    }

    /** Oublie les rôles mémorisés (après un changement de rôle). */
    public function forgetRoles(): void
    {
        $this->rolesByPressing = [];
        $this->subscriptionsByPressing = [];
    }

    /** RB-08 : un employé n'accède qu'aux pressings auxquels il est affecté. */
    public function belongsToPressing(Pressing $pressing): bool
    {
        return $this->pressings()
            ->where('pressings.id', $pressing->id)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function roleFor(Pressing $pressing): ?PressingRole
    {
        $pivot = $this->pressings()
            ->where('pressings.id', $pressing->id)
            ->wherePivot('is_active', true)
            ->first()
            ?->pivot;

        return $pivot?->role;
    }

    public function isAdminOf(Pressing $pressing): bool
    {
        return $this->roleFor($pressing) === PressingRole::Admin;
    }

    /**
     * Pressings actifs de ce membre du staff, triés par nom — pour le
     * sélecteur multi-pressing dans la sidebar.
     *
     * @return Collection<int, Pressing>
     */
    public function activePressings(): Collection
    {
        return $this->pressings()->wherePivot('is_active', true)->orderBy('name')->get();
    }

    /**
     * Pressings que ce membre administre (rôle Administrateur actif) : le
     * « groupe » qu'il pilote. Un responsable de zone n'administre que
     * quelques pressings, le propriétaire les administre tous.
     *
     * @return Collection<int, Pressing>
     */
    public function managedPressings(): Collection
    {
        return $this->pressings()
            ->wherePivot('is_active', true)
            ->wherePivot('role', PressingRole::Admin->value)
            ->orderBy('name')
            ->get();
    }

    /** @return list<int> */
    public function managedPressingIds(): array
    {
        return $this->managedPressings()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Pilote un groupe : administre au moins deux pressings, dont l'un au
     * moins a la gestion de plusieurs pressings (Premium ou option).
     */
    public function managesGroup(): bool
    {
        return $this->administersSeveralPressings()
            && $this->managedPressings()->contains(fn (Pressing $pressing) => $this->pressingHasFeature($pressing, Feature::Group));
    }

    /** Administre au moins deux pressings (avec ou sans la gestion de groupe). */
    public function administersSeveralPressings(): bool
    {
        return $this->pressings()
            ->wherePivot('is_active', true)
            ->wherePivot('role', PressingRole::Admin->value)
            ->count() > 1;
    }

    /** Un propriétaire multi-pressing voit une vue d'ensemble consolidée. */
    public function hasMultiplePressings(): bool
    {
        return $this->pressings()->wherePivot('is_active', true)->count() > 1;
    }

    /**
     * Pressing actif pour cette session de travail : celle explicitement
     * choisie via le sélecteur (session), sinon la première par défaut —
     * ce qui garde le comportement mono-pressing transparent pour un
     * membre du staff qui n'a qu'un seul pressing.
     */
    /**
     * Exclut les comptes des pressings de test (gérant et équipe fictifs).
     *
     * @param  Builder<User>  $query
     */
    public function scopeOutsideSandboxes(Builder $query): void
    {
        $query->whereDoesntHave('pressings', fn (Builder $q) => $q->whereNotNull('sandbox_expires_at'));
    }

    public function currentPressing(): ?Pressing
    {
        $activeId = session('active_pressing_id');

        if ($activeId !== null) {
            $selected = $this->pressings()
                ->wherePivot('is_active', true)
                ->where('pressings.id', $activeId)
                ->first();

            if ($selected !== null) {
                return $selected;
            }
        }

        return $this->pressings()->wherePivot('is_active', true)->first();
    }
}
