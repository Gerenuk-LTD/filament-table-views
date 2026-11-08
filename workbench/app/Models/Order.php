<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'placed_at' => 'datetime',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSummaryAttribute(): string
    {
        return "{$this->reference} ({$this->total})";
    }
}
