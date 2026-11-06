<?php

namespace App\Models;

use Database\Factories\OrderPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Photo d'une commande (les articles déposés), visible par le pressing et
 * par le client dans l'application. Fichiers privés, servis par lien signé.
 */
class OrderPhoto extends Model
{
    /** @use HasFactory<OrderPhotoFactory> */
    use HasFactory;

    public const DISK = 'local';

    public const MAX_PER_ORDER = 30;

    public const MAX_SIDE = 1600;

    public const THUMB_SIDE = 400;

    /** Jours de conservation après le retrait (ou l'annulation) de la commande. */
    public const RETENTION_DAYS = 30;

    protected $fillable = [
        'order_id',
        'path',
        'thumb_path',
        'size_bytes',
        'uploaded_by',
    ];

    /** @var list<string> */
    protected $hidden = ['path', 'thumb_path'];

    protected static function booted(): void
    {
        static::deleted(function (self $photo): void {
            Storage::disk(self::DISK)->delete([$photo->path, $photo->thumb_path]);
        });
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Dossier des photos d'un pressing (supprimé avec ses données). */
    public static function directoryFor(int $pressingId, ?int $orderId = null): string
    {
        return 'order-photos/'.$pressingId.($orderId === null ? '' : '/'.$orderId);
    }

    public function url(bool $thumb = false, int $minutes = 720): string
    {
        return URL::temporarySignedRoute('order-photos.show', now()->addMinutes($minutes), array_filter([
            'photo' => $this->id,
            'miniature' => $thumb ? 1 : null,
        ]));
    }

    /**
     * Pour l'application client : liens valables 24 h.
     *
     * @return array{id: int, url: string, thumb_url: string, taken_at: string}
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(minutes: 1440),
            'thumb_url' => $this->url(thumb: true, minutes: 1440),
            'taken_at' => $this->created_at->toIso8601String(),
        ];
    }
}
