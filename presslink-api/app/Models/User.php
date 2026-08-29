<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PressingRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Membre du staff d'un ou plusieurs pressings (employé ou admin).
 * Distinct de Customer (client final, authentifié par téléphone).
 */
#[Fillable(['name', 'email', 'phone', 'login', 'password', 'is_super_admin'])]
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
        ];
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
