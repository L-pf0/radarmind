<?php

namespace App\Support;

use App\Models\Alerta;
use App\Models\Os;
use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;

/**
 * Cria as linhas da tabela "alertas" quando cada situação acontece no sistema
 * (no módulo original isso era feito por INSERT direto no MySQL).
 * Chamado pelos observers de Item, PedidoReposicao e Os.
 */
class GeradorDeAlertas
{
    /**
     * Estoque entrou em nível baixo ou crítico (só avisa quando o nível muda, para não repetir).
     */
    public function estoqueMudou(Item $item, string $nivelAnterior): void
    {
        $nivel = $item->alert_level;

        if ($item->status === 'Inativo' || $nivel === $nivelAnterior || $nivel === 'Normal') {
            return;
        }

        $this->criar($item->unit_id, $nivel === 'Crítico' ? 'ESTOQUE_CRITICO' : 'ESTOQUE_BAIXO', $item, [
            'alto_giro' => $item->turnover === 'Alto giro',
            'qtd_atual' => $item->quantity,
            'qtd_minima' => $item->minimum_quantity,
        ]);
    }

    /**
     * Maquinário foi para manutenção (parado) ou voltou a operar.
     */
    public function maquinarioMudouDeStatus(Item $item, ?string $statusAnterior): void
    {
        if ($item->category !== 'maq' || $item->status === $statusAnterior) {
            return;
        }

        if ($item->status === 'Em manutenção') {
            $this->criar($item->unit_id, 'MAQUINARIO_QUEBRADO', $item);
        } elseif ($statusAnterior === 'Em manutenção' && $item->status === 'Ativo') {
            $this->criar($item->unit_id, 'MANUTENCAO_CONCLUIDA', $item);
        }
    }

    public function pedidoCriado(PedidoReposicao $pedido): void
    {
        if ($pedido->unit_id === null) {
            return;
        }

        $this->criar($pedido->unit_id, 'PEDIDO_REPOSICAO', $pedido->itemDoEstoque, [
            'referencia_id' => $pedido->id,
            'item_nome' => $pedido->itemDoEstoque?->name ?? $pedido->item,
        ]);
    }

    /**
     * Pedido atendido = a reposição chegou: avisa a unidade quanto entrou e o saldo novo do item.
     */
    public function pedidoMudouDeStatus(PedidoReposicao $pedido): void
    {
        if ($pedido->unit_id === null || $pedido->status !== 'Atendido') {
            return;
        }

        // Item recarregado: o saldo já inclui a quantidade que acabou de chegar
        $item = $pedido->item_id ? Item::find($pedido->item_id) : null;

        $this->criar($pedido->unit_id, 'PEDIDO_APROVADO', $item, [
            'referencia_id' => $pedido->id,
            'item_nome' => $item?->name ?? $pedido->item,
            'qtd_recebida' => $pedido->quantidade,
            'qtd_atual' => $item?->quantity,
            'qtd_minima' => $item?->minimum_quantity,
            'assinado_por' => $pedido->assinante_chegada, // quem assinou o recebimento
        ]);
    }

    public function osCriada(Os $os): void
    {
        if ($os->unit_id === null) {
            return;
        }

        $this->criar($os->unit_id, 'OS_ABERTA', null, [
            'referencia_id' => $os->id,
            'item_nome' => $os->maquina,
        ]);
    }

    /**
     * A matriz marcou a O.S. como "Feita": avisa a unidade que fez o registro.
     */
    public function osFinalizada(Os $os): void
    {
        if ($os->unit_id === null) {
            return;
        }

        $this->criar($os->unit_id, 'OS_FINALIZADA', null, [
            'referencia_id' => $os->id,
            'item_nome' => $os->maquina,
            // a tabela não tem coluna própria: em O.S. finalizada, assinado_por guarda quem pediu a O.S.
            // (o AlertaResource devolve esse valor como "solicitante")
            'assinado_por' => $os->solicitante,
        ]);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function criar(int $unitId, string $tipoEvento, ?Item $item, array $dados = []): void
    {
        Alerta::create($dados + [
            'tipo_evento' => $tipoEvento,
            'unit_id' => $unitId,
            'item_id' => $item?->id,
            'item_nome' => $item?->name,
            'item_codigo' => $item?->sku,
            'alto_giro' => $item?->turnover === 'Alto giro',
        ]);
    }
}
