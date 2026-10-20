<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Auth;

/**
 * Rattachement d'un client à un pressing. Chaque nouveau rattachement est
 * tracé : ajouté par un membre de l'équipe, ou client venu de l'application.
 */
class PressingCustomer extends Pivot
{
    protected static function booted(): void
    {
        static::created(function (self $link): void {
            $customer = Customer::find($link->customer_id);
            if ($customer === null) {
                return;
            }

            $who = $customer->fullName().' ('.PhoneNumber::format($customer->phone).')';
            ActivityLog::record($link->pressing_id, ActivityAction::CustomerAdded, Auth::guard('web')->check()
                ? "Client {$who} ajouté"
                : "Client {$who} inscrit depuis l'application", null, ['customer_id' => $customer->id]);
        });
    }

    protected $table = 'pressing_customers';

    protected $fillable = ['pressing_id', 'customer_id', 'joined_at'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }
}
