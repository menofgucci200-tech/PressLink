<?php

namespace App\Models;

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
     * @param  array{status?: ?string, search?: ?string, date_from?: ?string, date_to?: ?string}  $filters
     * @return HasMany<Order, $this>
     */
    public function filteredOrders(array $filters = []): HasMany
    {
        $query = $this->orders()->with('customer');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            // MySQL (prod) et SQLite (tests) n'utilisent pas la même syntaxe
            // pour extraire un format de date — DATE_FORMAT vs strftime.
            $isSqlite = $this->getConnection()->getDriverName() === 'sqlite';
            [$dmy, $dm] = $isSqlite
                ? ["strftime('%d/%m/%Y', dropped_off_at)", "strftime('%d/%m', dropped_off_at)"]
                : ["DATE_FORMAT(dropped_off_at, '%d/%m/%Y')", "DATE_FORMAT(dropped_off_at, '%d/%m')"];

            $query->where(function (Builder $q) use ($term, $dmy, $dm) {
                $q->where('order_number', 'like', "%{$term}%")
                    ->orWhere('total_fcfa', 'like', "%{$term}%")
                    // Date de dépôt, dans les deux formats qu'un utilisateur
                    // tape naturellement (avec ou sans l'année).
                    ->orWhereRaw("{$dmy} LIKE ?", ["%{$term}%"])
                    ->orWhereRaw("{$dm} LIKE ?", ["%{$term}%"])
                    ->orWhereHas('customer', function (Builder $c) use ($term) {
                        $c->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('dropped_off_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('dropped_off_at', '<=', $filters['date_to']);
        }

        return $query;
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
