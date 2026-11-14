<?php

namespace App\Http\Resources\Subempresa;

use App\Models\RadarMind\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class MovimentacaoResource extends JsonResource
{
    /**
     * Sempre envelopa em "data", mesmo tendo um campo chamado "data" (a data do registro).
     */
    public static bool $forceWrapping = true;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'data' => $this->movement_date->toDateString(),
            'itemCodigo' => $this->item->sku,
            'itemNome' => $this->item->name,
            'tipo' => $this->type,
            'quantidade' => $this->quantity,
        ];
    }
}
