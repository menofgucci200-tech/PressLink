<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Photo jointe par le client à sa demande de ramassage (le linge à
 * ramasser). Fichier privé, servi par lien signé au client et au pressing.
 */
class PickupRequestPhoto extends Model
{
    public const DISK = 'local';

    public const MAX_PER_REQUEST = 6;

    protected $fillable = [
        'pickup_request_id',
        'path',
        'thumb_path',
        'size_bytes',
    ];

    /** @var list<string> */
    protected $hidden = ['path', 'thumb_path'];

    protected static function booted(): void
    {
        static::deleted(function (self $photo): void {
            Storage::disk(self::DISK)->delete([$photo->path, $photo->thumb_path]);
        });
    }

    /** @return BelongsTo<PickupRequest, $this> */
    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class);
    }

    /** Dossier des photos d'une demande encore ouverte à la zone (sans pressing). */
    public const ZONE_DIRECTORY = 'zone';

    /** Dossier des photos de ramassage d'un pressing (supprimé avec ses données). */
    public static function directoryFor(int|string $pressingId, ?int $requestId = null): string
    {
        return 'pickup-photos/'.$pressingId.($requestId === null ? '' : '/'.$requestId);
    }

    public function url(bool $thumb = false, int $minutes = 720): string
    {
        return URL::temporarySignedRoute('pickup-photos.show', now()->addMinutes($minutes), array_filter([
            'photo' => $this->id,
            'miniature' => $thumb ? 1 : null,
        ]));
    }

    /** @return array{id: int, url: string, thumb_url: string} */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(minutes: 1440),
            'thumb_url' => $this->url(thumb: true, minutes: 1440),
        ];
    }
}
