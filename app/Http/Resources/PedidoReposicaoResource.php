<?php

namespace App\Http\Resources;

use App\Models\PedidoReposicao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mantém o formato JSON da API Python original (para o JavaScript da tela),
 * com os campos novos: unidade, item do estoque, status e o que o usuário logado pode fazer.
 *
 * @mixin PedidoReposicao
 */
class PedidoReposicaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'solicitante' => $this->solicitante,
            'data_pedido' => $this->data_pedido?->toDateString(),
            'unit_id' => $this->unit_id,
            'responsavel_unidade' => $this->unit?->name ?? $this->unidade,
            'item_id' => $this->item_id,
            'item' => $this->item,
            'quantidade' => $this->quantidade,
            'responsavel_nome' => $this->responsavel_nome,
            'observacao' => $this->observacao,
            'status' => $this->status,
            'estoque_lancado' => $this->estoqueJaLancado(),
            // Quem assinou o recebimento da reposição (coluna "Assinante") e quando
            'assinante' => $this->assinante_chegada,
            'chegada_em' => $this->estoque_lancado_em?->format('d/m/Y H:i'),
            'criado_em' => $this->created_at?->toDateTimeString(),
            // A matriz altera qualquer pedido; a subempresa só os da unidade dela enquanto estão em aberto.
            // Depois que a reposição chegou (estoque lançado), o pedido fica travado para todos.
            'pode_editar' => ! $this->estoqueJaLancado()
                && ($user->ehMatriz() || ($this->pertenceAUnidadeDe($user) && $this->estaEmAberto())),
            'pode_mudar_status' => $user->ehMatriz() && ! $this->estoqueJaLancado(),
            // Subempresa: botão "Confirmar chegada" nos pedidos em aberto da própria unidade
            'pode_confirmar_chegada' => $this->estaEmAberto() && $this->podeConfirmarChegada($user),
        ];
    }
}
