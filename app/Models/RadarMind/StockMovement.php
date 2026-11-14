<?php

namespace App\Models\RadarMind;

use App\Models\Concerns\PertenceAUnidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use PertenceAUnidade;

    protected $table = 'radarmind_stock_movements';

    protected $fillable = ['item_id', 'unit_id', 'type', 'quantity', 'movement_date', 'notes', 'user_id'];

    protected $casts = ['movement_date' => 'date', 'quantity' => 'integer'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Quem registrou a movimentação. */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
