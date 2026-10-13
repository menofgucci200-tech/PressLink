<?php

namespace App\Models;

use App\Actions\Auth\ResetPasswordToDefault;
use App\Enums\Gender;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Le client final de l'app mobile — authentifié par téléphone + mot de
 * passe (RB : un numéro de téléphone correspond à un compte client unique).
 */
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mot de passe attribué par défaut à un client créé directement par le
     * staff (walk-in, sans passer par l'inscription dans l'app) — à
     * transmettre au client pour qu'il se connecte puis le change dans
     * son profil.
     */
    public const DEFAULT_WALK_IN_PASSWORD = ResetPasswordToDefault::DEFAULT_PASSWORD;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'password',
        'must_change_password',
        'gender',
        'email',
        'email_notifications',
        'photo_path',
        'fcm_token',
        'onesignal_player_id',
        'phone_verified_at',
        'last_login_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'gender' => Gender::class,
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'email_notifications' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
        );
    }

    /** @return BelongsToMany<Pressing, $this> */
    public function pressings(): BelongsToMany
    {
        return $this->belongsToMany(Pressing::class, 'pressing_customers')
            ->using(PressingCustomer::class)
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Emails de suivi : seulement si le client a un email et ne s'est pas
     * désinscrit (colonne absente = activé par défaut).
     */
    public function wantsOrderEmails(): bool
    {
        return filled($this->email) && $this->email_notifications !== false;
    }

    public function routeNotificationForOneSignal(): ?string
    {
        return $this->onesignal_player_id;
    }
}
