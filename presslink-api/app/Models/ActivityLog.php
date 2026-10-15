<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Une action sensible dans un pressing (voir ActivityAction). Écrite par
 * ActivityLog::record() depuis les modèles et les actions concernés.
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

    /** @param  array<string, mixed>  $meta */
    public static function record(int $pressingId, ActivityAction $action, string $description, ?Order $order = null, array $meta = []): self
    {
        return self::create([
            'pressing_id' => $pressingId,
            'user_id' => Auth::guard('web')->id(),
            'order_id' => $order?->id,
            'action' => $action,
            'description' => mb_substr($description, 0, 255),
            'meta' => $meta ?: null,
        ]);
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
