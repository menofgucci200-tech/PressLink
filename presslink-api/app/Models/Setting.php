<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Réglage de la plateforme (clé → valeur JSON), modifié depuis le Super
 * Admin. Lu une seule fois par requête.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    private const CACHE = 'presslink.settings';

    public static function read(string $key, mixed $default = null): mixed
    {
        if (! app()->bound(self::CACHE)) {
            try {
                $values = self::query()->get()->pluck('value', 'key')->all();
            } catch (QueryException) {
                // Table absente (base pas encore migrée) : réglages par défaut.
                $values = [];
            }
            app()->instance(self::CACHE, $values);
        }

        return app(self::CACHE)[$key] ?? $default;
    }

    public static function write(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        app()->forgetInstance(self::CACHE);
    }
}
