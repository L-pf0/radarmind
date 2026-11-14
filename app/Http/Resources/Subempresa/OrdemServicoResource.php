<?php

namespace App\Http\Resources\Subempresa;

use App\Models\Os;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O.S. da tabela ordens_servico (a mesma que a matriz edita em "Registros de O.S.").
 *
 * @mixin Os
 */
class OrdemServicoResource extends JsonResource
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
            'maquina' => $this->maquina,
            'tipoProblema' => $this->tipo_problema,
            'descricao' => $this->descricao,
            'prioridade' => $this->prioridade,
            'status' => $this->status,
            'data' => $this->created_at?->toDateString(),
        ];
    }
}
