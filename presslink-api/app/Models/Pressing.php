<?php

namespace App\Models;

use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use Database\Factories\PressingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Pressing extends Model
{
    /** @use HasFactory<PressingFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'logo_path',
        'phone',
        'email',
        'address',
        'city',
        'description',
        'opening_hours',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'status' => PressingStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $pressing): void {
            $pressing->code ??= self::generateUniqueCode();
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = mb_strtoupper(Str::random(2)).'-'.random_int(1000, 9999);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasOne<Subscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /** @return BelongsToMany<Customer, $this> */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'pressing_customers')
            ->using(PressingCustomer::class)
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pressing_users')
            ->using(PressingUser::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->staff()->wherePivot('role', PressingRole::Admin->value);
    }

    public function employees(): BelongsToMany
    {
        return $this->staff()->wherePivot('role', PressingRole::Employee->value);
    }
}
