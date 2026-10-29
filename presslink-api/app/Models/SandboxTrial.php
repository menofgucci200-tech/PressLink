<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un test lancé depuis le site (« Découvrir avec un pressing fictif ») : le
 * nom du pressing et le WhatsApp du gérant. La fiche reste après la
 * suppression du pressing de test, pour que l'équipe puisse relancer le
 * gérant, et note s'il est devenu un vrai pressing.
 */
class SandboxTrial extends Model
{
    protected $fillable = [
        'pressing_id',
        'pressing_name',
        'contact_phone',
        'ip_address',
        'expires_at',
        'converted_pressing_id',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return BelongsTo<Pressing, $this> */
    public function convertedPressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class, 'converted_pressing_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }

    /** Test encore utilisable : ni expiré, ni transformé en vrai compte, pressing toujours là. */
    public function isRunning(): bool
    {
        return ! $this->isConverted() && $this->pressing_id !== null && $this->expires_at->isFuture();
    }

    /** « En cours », « Devenu client » ou « Terminé ». */
    public function statusLabel(): string
    {
        return match (true) {
            $this->isConverted() => 'Devenu client',
            $this->isRunning() => 'En cours',
            default => 'Terminé',
        };
    }
}
