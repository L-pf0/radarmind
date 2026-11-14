<?php

namespace App\Http\Controllers;

use App\Http\Resources\Subempresa\ItemResource;
use App\Http\Resources\Subempresa\MovimentacaoResource;
use App\Http\Resources\Subempresa\OrdemServicoResource;
use App\Http\Resources\Subempresa\ReposicaoResource;
use App\Models\Alerta;
use App\Models\Os;
use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\StockMovement;
use App\Models\RadarMind\Unit;
use App\Support\MedidorDeDesempenho;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

/**
 * Dashboard da Subempresa (antes um front-end separado + API em Python/Node com dados em memória).
 *
 * Só mostra o resumo da unidade do usuário logado. Os cadastros (estoque, movimentações,
 * O.S. e pedidos de reposição) são feitos nas páginas do sistema, as mesmas que a matriz usa.
 */
class SubempresaController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.subempresa', ['unidade' => $this->unidade($request)]);
    }

    public function dashboard(Request $request, MedidorDeDesempenho $medidorDeDesempenho): JsonResponse
    {
        $unidade = $this->unidade($request);
        $itens = $unidade->items()->get();
        $desempenho = $medidorDeDesempenho->medirUnidade($unidade);

        $alertas = $itens
            ->filter(fn (Item $item) => $item->status !== 'Inativo' && $item->alert_level !== 'Normal')
            ->sortBy('quantity')
            ->values();

        $recentes = StockMovement::with('item')
            ->where('unit_id', $unidade->id)
            ->latest('movement_date')
            ->latest('id')
            ->take(6)
            ->get();

        return response()->json([
            'subempresa' => ['id' => $unidade->id, 'nome' => $unidade->name],
            'kpis' => [
                'totalItens' => $itens->count(),
                'itensEmAlerta' => $alertas->count(),
                'osAbertas' => Os::where('unit_id', $unidade->id)->where('status', 'Aguardando autorização')->count(),
                'reposicoesPendentes' => PedidoReposicao::where('unit_id', $unidade->id)->where('status', 'Em aberto')->count(),
            ],
            // Mesmo cálculo do ranking da matriz: movimentações registradas + itens de alto giro em estoque
            'desempenho' => [
                'pontuacao' => $desempenho['pontuacao'],
                'movimentacoes' => $desempenho['movimentacoes'],
                'itensAltoGiro' => $desempenho['itens_alto_giro'],
                'pontosMovimentacoes' => $desempenho['pontos_movimentacoes'],
                'pontosAltoGiro' => $desempenho['pontos_alto_giro'],
                'pesoPorCriterio' => MedidorDeDesempenho::PESO_MOVIMENTACOES,
            ],
            // Central de Notificações: só os alertas da própria unidade (não lidos primeiro)
            'alertas' => Alerta::where('unit_id', $unidade->id)
                ->whereIn('tipo_evento', array_keys(Alerta::EVENTOS))
                ->orderBy('lido')
                ->orderByDesc('data_criacao')
                ->orderByDesc('id')
                ->take(5)
                ->get()
                ->map(fn (Alerta $alerta) => [
                    'titulo' => $alerta->texto('titulo'),
                    'gravidade' => $alerta->modelo()['gravidade'],
                    'tempo' => $alerta->tempoRelativo(),
                    'lido' => $alerta->lido,
                ]),
            'alertasNaoLidos' => Alerta::where('unit_id', $unidade->id)->where('lido', false)->count(),
            'movimentacoesRecentes' => MovimentacaoResource::collection($recentes)->resolve($request),
        ]);
    }

    public function estoque(Request $request): AnonymousResourceCollection
    {
        $itens = $this->unidade($request)->items()->orderBy('category')->orderBy('name')->get();

        return ItemResource::collection($itens);
    }

    public function movimentacoes(Request $request): AnonymousResourceCollection
    {
        $movimentacoes = StockMovement::with('item')
            ->where('unit_id', $this->unidade($request)->id)
            ->latest('movement_date')
            ->latest('id')
            ->get();

        return MovimentacaoResource::collection($movimentacoes);
    }

    /**
     * O.S. da unidade (a mesma lista de "Registros de O.S."; só a matriz muda o status).
     */
    public function ordensServico(Request $request): AnonymousResourceCollection
    {
        $ordens = Os::where('unit_id', $this->unidade($request)->id)->latest()->take(6)->get();

        return OrdemServicoResource::collection($ordens);
    }

    /**
     * Pedidos de reposição da unidade (página "Pedidos de reposição").
     */
    public function reposicoes(Request $request): AnonymousResourceCollection
    {
        $pedidos = PedidoReposicao::where('unit_id', $this->unidade($request)->id)
            ->latest('data_pedido')
            ->latest('id')
            ->take(6)
            ->get();

        return ReposicaoResource::collection($pedidos);
    }

    /**
     * Unidade da subempresa logada. Sem unidade vinculada, não há o que mostrar.
     */
    private function unidade(Request $request): Unit
    {
        return $request->user()->unidadeObrigatoria();
    }
}
