<?php

namespace App\Models;

use App\Enums\OrderIssueCategory;
use App\Enums\OrderIssueStatus;
use Database\Factories\OrderIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Problème signalé par un client sur une commande — RB-02 : un client ne
 * peut signaler que ses propres commandes (contrôlé par le controller).
 */
class OrderIssue extends Model
{
    /** @use HasFactory<OrderIssueFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'customer_id',
        'category',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'category' => OrderIssueCategory::class,
            'status' => OrderIssueStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
