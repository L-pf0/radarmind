<?php

namespace App\Http\Resources\Subempresa;

use App\Models\PedidoReposicao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pedido de reposição (tabela pedidos_reposicao) no formato do dashboard da subempresa.
 *
 * @mixin PedidoReposicao
 */
class ReposicaoResource extends JsonResource
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
            'item' => $this->item,
            'quantidade' => $this->quantidade,
            'observacao' => $this->observacao,
            'status' => $this->status,
            'data' => $this->data_pedido?->toDateString(),
        ];
    }
}
