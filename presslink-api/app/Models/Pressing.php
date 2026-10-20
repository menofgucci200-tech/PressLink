<?php

namespace App\Models;

use App\Actions\Demo\ResetDemoPressing;
use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Exceptions\PressingClosedException;
use Carbon\Carbon;
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
        'exceptional_open_until',
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
            'exceptional_open_until' => 'datetime',
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

        // Tout pressing démarre avec deux services : Repassage et Nettoyage.
        static::created(fn (self $pressing) => Prestation::createDefaultsFor($pressing));
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
     * (Pressing\Settings) ou une ouverture exceptionnelle de l'administrateur ?
     * Indépendant du statut de compte/abonnement (`status`). Sans horaires
     * renseignés, le pressing est considéré comme toujours ouvert.
     */
    public function isOpenNow(): bool
    {
        return $this->isExceptionallyOpen() || $this->isOnSchedule(now());
    }

    /** Ouverture exceptionnelle en cours (décidée par l'administrateur). */
    public function isExceptionallyOpen(): bool
    {
        return $this->exceptional_open_until !== null && $this->exceptional_open_until->isFuture();
    }

    /** Le moment donné tombe-t-il dans les horaires du jour ? (fermeture à l'heure pile) */
    public function isOnSchedule(Carbon $at): bool
    {
        if (empty($this->opening_hours)) {
            return true;
        }

        $hours = $this->hoursOn($at);
        if ($hours === null) {
            return false;
        }

        $time = $at->format('H:i');

        return $time >= $hours['open'] && $time < $hours['close'];
    }

    /**
     * Le pressing accueille-t-il des clients en ce moment (commandes,
     * paiements, remises, annulations, nouveaux clients) ? Le pressing de
     * démonstration reste toujours ouvert, pour être essayé à toute heure.
     */
    public function acceptsCustomers(): bool
    {
        return $this->code === ResetDemoPressing::PRESSING_CODE || $this->isOpenNow();
    }

    /**
     * @throws PressingClosedException le pressing est fermé
     */
    public function ensureAcceptsCustomers(): void
    {
        if (! $this->acceptsCustomers()) {
            throw new PressingClosedException($this->closedMessage());
        }
    }

    /** « Pressing fermé : réouverture demain à 08:00. » */
    public function closedMessage(): string
    {
        $next = $this->nextOpeningLabel();

        return 'Pressing fermé'.($next ? " : réouverture {$next}" : '').'. Les commandes, paiements et remises reprendront à l\'ouverture.';
    }

    /** Prochaine ouverture d'après les horaires (dans les 7 jours), ou null. */
    public function nextOpeningAt(): ?Carbon
    {
        if (empty($this->opening_hours)) {
            return null;
        }

        $now = now();
        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $now->copy()->addDays($offset);
            $hours = $this->hoursOn($day);
            if ($hours === null) {
                continue;
            }

            $opening = $day->copy()->setTimeFromTimeString($hours['open']);
            if ($opening->greaterThan($now)) {
                return $opening;
            }
        }

        return null;
    }

    /** « aujourd'hui à 14:00 », « demain à 08:00 », « lundi à 07:30 ». */
    public function nextOpeningLabel(): ?string
    {
        $next = $this->nextOpeningAt();
        if ($next === null) {
            return null;
        }

        $day = match (true) {
            $next->isToday() => 'aujourd\'hui',
            $next->isTomorrow() => 'demain',
            default => mb_strtolower($next->locale('fr')->dayName),
        };

        return $day.' à '.$next->format('H:i');
    }

    /** Heure de fermeture du jour (ou de fin d'ouverture exceptionnelle), si le pressing est ouvert. */
    public function closesAt(): ?Carbon
    {
        if ($this->isExceptionallyOpen()) {
            return $this->exceptional_open_until;
        }

        $hours = empty($this->opening_hours) ? null : $this->hoursOn(now());

        return $hours !== null && $this->isOnSchedule(now()) ? now()->setTimeFromTimeString($hours['close']) : null;
    }

    /** @return array{open: string, close: string}|null Horaires d'un jour, null s'il est fermé. */
    private function hoursOn(Carbon $at): ?array
    {
        $day = mb_strtolower($at->copy()->locale('fr')->dayName);
        $hours = $this->opening_hours[$day] ?? null;

        if ($hours === null || ($hours['closed'] ?? false) || empty($hours['open']) || empty($hours['close'])) {
            return null;
        }

        return ['open' => $hours['open'], 'close' => $hours['close']];
    }

    /** @return HasMany<Prestation, $this> */
    public function prestations(): HasMany
    {
        return $this->hasMany(Prestation::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Services et prix visibles par les clients : pour chaque service actif, les
     * pièces actives qui y ont un prix (variantes comprises).
     *
     * @return list<array{id: int, name: string, icon: string, pieces: list<array<string, mixed>>}>
     */
    public function publicCatalog(): array
    {
        $pieces = $this->services()->where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('name'), 'prices'])
            ->orderBy('name')->get();

        return $this->prestations()->where('is_active', true)->get()->map(function (Prestation $prestation) use ($pieces) {
            $listed = $pieces->map(function (Service $piece) use ($prestation) {
                $prices = $piece->prices->where('prestation_id', $prestation->id);
                $variants = $piece->variants
                    ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price_fcfa' => $prices->firstWhere('service_variant_id', $v->id)?->price_fcfa])
                    ->filter(fn (array $v) => $v['price_fcfa'] !== null)->values();
                $base = $prices->firstWhere('service_variant_id', null)?->price_fcfa;

                if ($variants->isEmpty() && $base === null) {
                    return null;
                }

                return [
                    'id' => $piece->id,
                    'name' => $piece->name,
                    'icon' => $piece->iconKey(),
                    'price_fcfa' => $variants->isNotEmpty() ? null : $base,
                    'variants' => $variants->all(),
                ];
            })->filter()->values()->all();

            return ['id' => $prestation->id, 'name' => $prestation->name, 'icon' => $prestation->iconKey(), 'pieces' => $listed];
        })->filter(fn (array $p) => $p['pieces'] !== [])->values()->all();
    }

    /** Service « Nettoyage » (ou, à défaut, le premier) : sert de service par défaut aux données de démonstration et de test. */
    public function cleaningPrestation(): ?Prestation
    {
        return $this->prestations()->where('icon', 'nettoyage')->first() ?? $this->prestations()->first();
    }

    /** Pièces du catalogue (Chemise, Robe, Drap…) — modèle `Service`, nom historique. @return HasMany<Service, $this> */
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
