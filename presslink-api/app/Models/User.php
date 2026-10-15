<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PressingRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
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

    /** Pilote un groupe : administre au moins deux pressings. */
    public function managesGroup(): bool
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
