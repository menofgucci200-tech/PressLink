<?php

namespace App\Models;

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

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'password',
        'gender',
        'email',
        'photo_path',
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
            'is_active' => 'boolean',
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
}
