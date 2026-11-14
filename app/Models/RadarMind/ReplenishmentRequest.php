<?php

namespace App\Models\RadarMind;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplenishmentRequest extends Model
{
    protected $table = 'radarmind_replenishment_requests';

    protected $fillable = ['unit_id', 'item_id', 'quantity', 'notes', 'status', 'requested_at', 'user_id'];

    protected $casts = ['requested_at' => 'date', 'quantity' => 'integer'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
