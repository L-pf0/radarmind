<?php

namespace App\Http\Resources;

use App\Models\Alerta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mesmo formato JSON da API Python original (alerta_para_json), para o alertas.js do grupo.
 *
 * @mixin Alerta
 */
class AlertaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $modelo = $this->modelo();
        $botao = $this->botaoPara($request->user());

        return [
            'id' => $this->id,
            'subempresa_id' => $this->unit_id,
            'subempresa_nome' => $this->unit?->name,
            'tipo_categoria' => $modelo['categoria'],
            'gravidade' => $modelo['gravidade'],
            'titulo' => $this->texto('titulo'),
            'data_criacao' => $this->data_criacao->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'lido' => $this->lido,
            'detalhes' => [
                'mensagem' => $this->texto('mensagem'),
                'item_nome' => $this->item_nome,
                'item_codigo' => $this->item_codigo,
                'alto_giro' => $this->alto_giro,
                'qtd_atual' => $this->qtd_atual,
                'qtd_minima' => $this->qtd_minima,
                // pedido atendido: quem assinou o recebimento e quantas unidades chegaram
                'assinado_por' => $this->tipo_evento === 'OS_FINALIZADA' ? null : $this->assinado_por,
                'qtd_recebida' => $this->qtd_recebida,
                // O.S. finalizada: a coluna assinado_por guarda quem pediu a O.S.
                'solicitante' => $this->tipo_evento === 'OS_FINALIZADA' ? $this->assinado_por : null,
            ],
            'redirecionamento' => [
                'rotulo_botao' => $botao['rotulo'],
                'url' => $botao['url'],
                'cor_botao' => 'btn-primary',
            ],
        ];
    }
}
