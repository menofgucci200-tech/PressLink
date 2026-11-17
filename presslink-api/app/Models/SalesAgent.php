<?php

namespace App\Models;

use Database\Factories\SalesAgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Commercial de terrain : il présente PressLink aux pressings, les inscrit
 * depuis son espace (ou les fait s'inscrire avec son code) et touche une
 * commission sur l'abonnement de chaque pressing qu'il a apporté.
 */
class SalesAgent extends Model
{
    /** @use HasFactory<SalesAgentFactory> */
    use HasFactory;

    /** Commission par défaut sur l'abonnement des pressings apportés (%). */
    public const DEFAULT_COMMISSION_RATE = 20;

    protected $fillable = ['user_id', 'code', 'commission_rate', 'suspended_at'];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'integer',
            'suspended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Pressing, $this> */
    public function pressings(): HasMany
    {
        return $this->hasMany(Pressing::class);
    }

    /** @return HasMany<SalesAgentClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(SalesAgentClaim::class);
    }

    public function isActive(): bool
    {
        return $this->suspended_at === null;
    }

    /** Taux commun des commissions (%), réglé dans la console. */
    public static function defaultCommissionRate(): int
    {
        return (int) Setting::read('sales_commission_rate', self::DEFAULT_COMMISSION_RATE);
    }

    /** Taux de ce commercial : le sien s'il en a un, sinon le taux commun. */
    public function commissionRate(): int
    {
        return $this->commission_rate ?? self::defaultCommissionRate();
    }

    /** Lien d'inscription à partager : le code est déjà rempli. */
    public function signupUrl(): string
    {
        return route('signup', ['code' => $this->code]);
    }

    /** Commercial actif dont c'est le code (insensible à la casse et aux espaces). */
    public static function findActiveByCode(?string $code): ?self
    {
        $code = self::normalizeCode($code);

        return $code === '' ? null : self::query()->where('code', $code)->whereNull('suspended_at')->first();
    }

    public static function normalizeCode(?string $code): string
    {
        return Str::upper(preg_replace('/\s+/', '', (string) $code));
    }

    /** Code court et lisible, tiré du nom : « AWA-4821 ». */
    public static function generateCode(string $name): string
    {
        $prefix = Str::upper(Str::limit(preg_replace('/[^A-Za-z]/', '', Str::ascii(Str::before(trim($name), ' '))), 6, '')) ?: 'PL';

        do {
            $code = $prefix.'-'.random_int(1000, 9999);
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
