<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OtpCode extends Model
{
    public $timestamps = true;

    protected $fillable = ['phone', 'code', 'expires_at', 'consumed_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public static function generateFor(string $phone, int $ttlMinutes = 5): self
    {
        return DB::transaction(function () use ($phone, $ttlMinutes) {
            self::where('phone', $phone)->whereNull('consumed_at')->delete();

            return self::create([
                'phone' => $phone,
                'code' => (string) random_int(100_000, 999_999),
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);
        });
    }

    public function isValid(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }

    public function consume(): void
    {
        $this->update(['consumed_at' => now()]);
    }
}
