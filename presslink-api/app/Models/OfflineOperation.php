<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Opération du comptoir hors connexion déjà appliquée, avec son résultat
 * (commande ou client créé, refus) : renvoyé tel quel si elle arrive encore.
 */
class OfflineOperation extends Model
{
    protected $fillable = ['uuid', 'pressing_id', 'user_id', 'type', 'result', 'performed_at'];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'performed_at' => 'datetime',
        ];
    }
}
