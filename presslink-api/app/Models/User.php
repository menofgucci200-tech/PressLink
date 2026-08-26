<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PressingRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Membre du staff d'un ou plusieurs pressings (employé ou admin).
 * Distinct de Customer (client final, authentifié par téléphone).
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
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
     * Pressing actif pour cette session de travail.
     * MVP : un membre du staff opère sur un seul pressing à la fois
     * (le multi-agences par utilisateur est hors périmètre MVP).
     */
    public function currentPressing(): ?Pressing
    {
        return $this->pressings()->wherePivot('is_active', true)->first();
    }
}
