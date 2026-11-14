<?php

namespace App\Models\RadarMind;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrder extends Model
{
    protected $table = 'radarmind_service_orders';

    protected $fillable = ['item_id', 'unit_id', 'title', 'description', 'priority', 'status', 'opened_at', 'closed_at', 'user_id'];

    protected $casts = ['opened_at' => 'date', 'closed_at' => 'date'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
