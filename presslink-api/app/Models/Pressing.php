<?php

namespace App\Models;

use App\Actions\Demo\ResetDemoPressing;
use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use Database\Factories\PressingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
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
        'show_pricing_to_customers',
        'status',
    ];

    protected $appends = ['logo_url'];

    /** Filtre « commandes non soldées » (acompte ou rien payé). */
    public const UNSETTLED_FILTER = 'non_soldee';

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'show_pricing_to_customers' => 'boolean',
            'status' => PressingStatus::class,
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $pressing): void {
            $pressing->code ??= self::generateUniqueCode();
        });
    }

    /**
     * Exclut le pressing de démonstration (statistiques de la console).
     *
     * @param  Builder<Pressing>  $query
     */
    public function scopeWithoutDemo(Builder $query): void
    {
        $query->where('code', '!=', ResetDemoPressing::PRESSING_CODE);
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = mb_strtoupper(Str::random(2)).'-'.random_int(1000, 9999);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Le pressing est-il ouvert à l'instant, d'après ses horaires
     * (Pressing\Settings) ? Indépendant du statut de compte/abonnement
     * (`status`), qui ne dit rien sur les horaires du jour.
     */
    public function isOpenNow(): bool
    {
        if (empty($this->opening_hours)) {
            return true;
        }

        $day = mb_strtolower(now()->locale('fr')->dayName);
        $today = $this->opening_hours[$day] ?? null;

        if ($today === null || ($today['closed'] ?? false)) {
            return false;
        }

        $now = now()->format('H:i');

        return $now >= ($today['open'] ?? '00:00') && $now <= ($today['close'] ?? '23:59');
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

    /**
     * Commandes du pressing filtrées — utilisé à la fois par l'affichage
     * (Orders\Index, Dashboard) et par les exports, pour garantir qu'un
     * export "avec filtres" contient exactement ce qui est affiché.
     *
     * @param  array{status?: ?string, search?: ?string, date_from?: ?string, date_to?: ?string, payment?: ?string}  $filters
     * @return HasMany<Order, $this>
     */
    public function filteredOrders(array $filters = []): HasMany
    {
        return $this->orders()->with('customer')->applyFilters($filters);
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
