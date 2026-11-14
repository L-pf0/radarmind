<?php

namespace App\Http\Requests;

use App\Models\RadarMind\Item;
use App\Models\RadarMind\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pedido de reposição (página feita pelo grupo em Python, agora o único pedido do sistema).
 *
 * - A matriz pede para si ou para qualquer subempresa.
 * - A subempresa pede só para si: a unidade é sempre a dela, mesmo que o formulário mande outra.
 * - O item tem que ser do estoque da unidade escolhida.
 */
class PedidoReposicaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()->ehMatriz()) {
            $this->merge(['unit_id' => $this->user()->unit_id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'solicitante' => ['required', 'string', 'max:120'],
            'data_pedido' => ['required', 'date_format:Y-m-d'],
            'unit_id' => ['required', 'integer', Rule::exists('radarmind_units', 'id')],
            'item_id' => ['required', 'integer', Rule::exists('radarmind_items', 'id')->where('unit_id', $this->integer('unit_id'))],
            'quantidade' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'responsavel_nome' => ['required', 'string', 'max:120'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'solicitante.required' => 'Informe o solicitante.',
            'solicitante.max' => 'O solicitante deve ter no máximo 120 caracteres.',
            'data_pedido.required' => 'Informe uma data válida.',
            'data_pedido.date_format' => 'Informe uma data válida.',
            'unit_id.*' => 'Escolha a unidade que vai receber a reposição.',
            'item_id.*' => 'Escolha um item do estoque da unidade selecionada.',
            'quantidade.*' => 'A quantidade deve ser um número inteiro maior que zero.',
            'responsavel_nome.required' => 'Informe o nome do responsável.',
            'responsavel_nome.max' => 'O nome do responsável deve ter no máximo 120 caracteres.',
            'observacao.max' => 'A observação deve ter no máximo 1000 caracteres.',
        ];
    }

    /**
     * Converte o formulário para as colunas da tabela pedidos_reposicao.
     *
     * @return array{solicitante: string, data_pedido: string, unit_id: int, unidade: string, item_id: int, item: string, quantidade: int, responsavel_nome: string, observacao: string|null}
     */
    public function dadosDoPedido(): array
    {
        $dados = $this->validated();
        $unidade = Unit::findOrFail($dados['unit_id']);
        $item = Item::findOrFail($dados['item_id']);

        return [
            'solicitante' => trim($dados['solicitante']),
            'data_pedido' => $dados['data_pedido'],
            'unit_id' => $unidade->id,
            'unidade' => $unidade->name,
            'item_id' => $item->id,
            'item' => "{$item->sku} · {$item->name}",
            'quantidade' => (int) $dados['quantidade'],
            'responsavel_nome' => trim($dados['responsavel_nome']),
            'observacao' => trim((string) ($dados['observacao'] ?? '')) ?: null,
        ];
    }
}
