<?php

namespace App\Http\Resources\Subempresa;

use App\Models\RadarMind\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item de estoque no formato usado pelo dashboard da subempresa.
 *
 * @mixin Item
 */
class ItemResource extends JsonResource
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
            'codigo' => $this->sku,
            'nome' => $this->name,
            'categoria' => $this->category === 'maq' ? 'Maquinário' : 'Peça',
            'ehMaquinario' => $this->category === 'maq',
            'quantidade' => $this->quantity,
            'minimo' => $this->minimum_quantity,
            'status' => $this->status,
            // Mesmo cálculo de alerta usado pela matriz (Item::alert_level)
            'nivel' => match ($this->alert_level) {
                'Crítico' => 'crítico',
                'Baixo' => 'atenção',
                default => 'normal',
            },
        ];
    }
}
