<?php

namespace App\Http\Controllers;

use App\Http\Requests\PedidoReposicaoRequest;
use App\Http\Resources\PedidoReposicaoResource;
use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pedidos de reposição (antes um back end Python/Flask separado). É o ÚNICO pedido de reposição
 * do sistema: o Estoque, os Alertas e os dashboards da matriz e da subempresa usam esta tabela.
 *
 * - A matriz vê todos os pedidos, pede para qualquer unidade e muda o status (atendido/cancelado).
 * - A subempresa confirma a chegada (atendido, com assinatura) dos pedidos da própria unidade.
 * - A subempresa vê e pede só para a própria unidade; edita/exclui os dela enquanto estão em aberto.
 */
class PedidoReposicaoController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Unidades que podem receber o pedido e os itens de estoque de cada uma (o JS filtra o item pela unidade)
        $unidades = $user->ehMatriz() ? Unit::orderBy('id')->get() : Unit::whereKey($user->unit_id)->get();
        $itens = Item::whereIn('unit_id', $unidades->pluck('id'))->where('status', '!=', 'Inativo')->orderBy('name')->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'unit_id' => $item->unit_id,
                'texto' => "{$item->sku} · {$item->name} (saldo {$item->quantity})",
                'saldo' => $item->quantity, // usado no modal de chegada: "saldo atual → saldo depois"
            ]);

        return view('pedidos-reposicao.index', [
            'unidades' => $unidades,
            'itens' => $itens,
            'ehMatriz' => $user->ehMatriz(),
            'minhaUnidadeId' => $user->unit_id,
            'itemSugerido' => $request->integer('item') ?: null,
            // ?pedido=ID (vindo de um alerta ou do Estoque): a matriz já cai no modal de confirmar a chegada
            'pedidoSugerido' => $request->integer('pedido') ?: null,
            'statusLista' => PedidoReposicao::STATUS,
        ]);
    }

    public function listar(Request $request): AnonymousResourceCollection
    {
        $pedidos = PedidoReposicao::with('unit')->visivelPara($request->user())->latest('id')->get();

        return PedidoReposicaoResource::collection($pedidos);
    }

    public function store(PedidoReposicaoRequest $request): JsonResponse
    {
        $pedido = PedidoReposicao::create($request->dadosDoPedido() + [
            'status' => 'Em aberto',
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'mensagem' => 'Pedido de reposição registrado com sucesso!',
            'id' => $pedido->id,
        ], 201);
    }

    public function update(PedidoReposicaoRequest $request, PedidoReposicao $pedido): JsonResponse
    {
        $this->garantirQuePodeEditar($request, $pedido);

        $pedido->update($request->dadosDoPedido());

        return response()->json(['mensagem' => 'Pedido atualizado com sucesso!']);
    }

    public function destroy(Request $request, PedidoReposicao $pedido): JsonResponse
    {
        $this->garantirQuePodeEditar($request, $pedido);

        $pedido->delete();

        return response()->json(['mensagem' => 'Pedido excluído com sucesso!']);
    }

    /**
     * Matriz: Em aberto / Atendido / Cancelado em qualquer pedido.
     * Subempresa: só confirma a chegada ("Atendido") dos pedidos da própria unidade.
     * "Atendido" = a reposição chegou: a quantidade entra no estoque (PedidoReposicao::mudarStatus).
     * Responde JSON para a página de pedidos e volta para a tela anterior quando vem de um formulário.
     */
    public function atualizarStatus(Request $request, PedidoReposicao $pedido): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user->ehMatriz()) {
            abort_unless($pedido->pertenceAUnidadeDe($user), 403, 'Você só pode confirmar a chegada dos pedidos da sua unidade.');
            abort_unless($request->input('status') === 'Atendido', 403, 'Só a matriz pode cancelar ou reabrir um pedido.');
        }

        $dados = $request->validate([
            'status' => ['required', Rule::in(PedidoReposicao::STATUS)],
            // Nome de quem assinou o recebimento (modal "Confirmar chegada do estoque")
            'assinante' => ['nullable', 'required_if:status,Atendido', 'string', 'max:120'],
        ], [
            'assinante.required_if' => 'Informe o nome de quem confirmou a chegada da reposição.',
            'assinante.max' => 'O nome do assinante deve ter no máximo 120 caracteres.',
        ]);

        $pedido->mudarStatus($dados['status'], $user, $dados['assinante'] ?? null);

        $mensagem = $dados['status'] === 'Atendido'
            ? "Chegada confirmada por {$pedido->assinante_chegada}: {$pedido->quantidade} unidade(s) entraram no estoque de {$pedido->item}."
            : "Pedido marcado como {$dados['status']}.";

        if ($request->expectsJson()) {
            return response()->json(['mensagem' => $mensagem]);
        }

        return back()->with('success', $mensagem);
    }

    /**
     * Matriz: qualquer pedido. Subempresa: só os da unidade dela e ainda em aberto
     * (bloqueia também quem tenta trocar o ID pela URL).
     * Depois que a reposição chegou (estoque lançado), ninguém edita nem exclui o pedido.
     */
    private function garantirQuePodeEditar(Request $request, PedidoReposicao $pedido): void
    {
        $user = $request->user();

        abort_if(
            $pedido->estoqueJaLancado(),
            403,
            'Este pedido já foi atendido e a quantidade já entrou no estoque; ele não pode mais ser alterado.'
        );

        abort_unless(
            $user->ehMatriz() || ($pedido->pertenceAUnidadeDe($user) && $pedido->estaEmAberto()),
            403,
            'Você só pode alterar pedidos da sua unidade que ainda estão em aberto.'
        );
    }
}
