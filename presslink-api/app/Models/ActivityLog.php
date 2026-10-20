<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Une ligne du journal d'activité d'un pressing (voir ActivityAction) : qui,
 * quoi, quand. Écrite par ActivityLog::record() depuis les modèles et les
 * actions concernés. Le nom et le rôle de l'auteur sont recopiés au moment de
 * l'action, pour que le journal reste juste même si le rôle change ensuite.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['pressing_id', 'user_id', 'order_id', 'action', 'description', 'meta'];

    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** Journal mis en pause (ex. harmonisation d'un catalogue : une seule ligne de synthèse). */
    private static bool $muted = false;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutLogging(callable $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    /** @param  array<string, mixed>  $meta */
    public static function record(int $pressingId, ActivityAction $action, string $description, ?Order $order = null, array $meta = []): ?self
    {
        if (self::$muted) {
            return null;
        }

        $user = Auth::guard('web')->user();
        if ($user !== null && ! $user->is_super_admin && ($pressing = Pressing::find($pressingId)) !== null) {
            $meta = ['by_role' => $user->roleLabelIn($pressing), ...$meta];
        } elseif ($user?->is_super_admin) {
            $meta = ['by_role' => 'Support PressLink', ...$meta];
        }

        return self::create([
            'pressing_id' => $pressingId,
            'user_id' => $user?->id,
            'order_id' => $order?->id,
            'action' => $action,
            'description' => mb_substr($description, 0, 255),
            'meta' => $meta ?: null,
        ]);
    }

    /** Rôle de l'auteur au moment de l'action (« Gérant », « Employé »…). */
    public function authorRole(): ?string
    {
        return $this->meta['by_role'] ?? null;
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}
