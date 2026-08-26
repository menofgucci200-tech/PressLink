<?php

namespace App\Models;

use App\Enums\PressingRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PressingUser extends Pivot
{
    protected $table = 'pressing_users';

    protected $fillable = ['pressing_id', 'user_id', 'role', 'is_active'];

    protected function casts(): array
    {
        return [
            'role' => PressingRole::class,
            'is_active' => 'boolean',
        ];
    }
}
