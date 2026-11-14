<?php

namespace App\Models\RadarMind;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $table = 'radarmind_units';

    protected $fillable = ['name', 'code'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
