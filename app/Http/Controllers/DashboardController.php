<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\StockMovement;
use App\Models\RadarMind\Unit;
use App\Support\MedidorDeDesempenho;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Alimenta o Dashboard Matriz com os dados do módulo operacional
 * (estoque, movimentações e pedidos de reposição).
 */
class DashboardController extends Controller
{
    /**
     * Cores usadas nos pontinhos das unidades (classes dot-* do app.css).
     */
    private const CORES = ['blue', 'green', 'orange', 'purple'];

    public function __construct(private MedidorDeDesempenho $medidorDeDesempenho) {}

    public function matriz(): View
    {
        $units = Unit::orderBy('id')->get();
        $itens = Item::with('unit')->get();
        $cores = $units->values()->mapWithKeys(fn (Unit $unit, int $i) => [$unit->id => self::CORES[$i % count(self::CORES)]]);

        return view('dashboard.matriz', [
            'stats' => [
                'itens_estoque' => $itens->sum('quantity'),
                'movimentacoes' => StockMovement::count(),
            ],
            'ranking' => $this->ranking($units, $cores),
            'alertas' => $this->alertas(),
            'alertasNaoLidos' => Alerta::where('lido', false)->count(),
            'movimentacoes' => $this->ultimasMovimentacoes($cores),
            'pedidos' => $this->pedidosEmAberto($cores),
        ]);
    }

    /**
     * Pontuação de cada unidade: movimentações registradas + itens de alto giro em estoque.
     *
     * @param  Collection<int, Unit>  $units
     * @param  Collection<int, string>  $cores
     * @return Collection<int, array{nome: string, pontuacao: int, movimentacoes: int, itens_alto_giro: int, cor: string}>
     */
    private function ranking(Collection $units, Collection $cores): Collection
    {
        return $this->medidorDeDesempenho->medir($units)
            ->map(fn (array $desempenho) => [
                'nome' => $desempenho['unidade']->name,
                'pontuacao' => $desempenho['pontuacao'],
                'movimentacoes' => $desempenho['movimentacoes'],
                'itens_alto_giro' => $desempenho['itens_alto_giro'],
                'cor' => $cores[$desempenho['unidade']->id],
            ]);
    }

    /**
     * Alertas da Central de Notificações de TODAS as unidades (a matriz vê os próprios e os das subempresas).
     * Não lidos primeiro, depois os mais recentes.
     *
     * @return Collection<int, array{tipo: string, unidade: string, descricao: string, categoria: string, tempo: string}>
     */
    private function alertas(): Collection
    {
        $tipoPorGravidade = ['CRITICO' => 'danger', 'ALERTA' => 'warning', 'CONCLUIDO' => 'success'];

        return Alerta::with('unit')
            ->whereIn('tipo_evento', array_keys(Alerta::EVENTOS))
            ->orderBy('lido')
            ->orderByDesc('data_criacao')
            ->orderByDesc('id')
            ->take(5)
            ->get()
            ->map(fn (Alerta $alerta) => [
                'tipo' => $tipoPorGravidade[$alerta->modelo()['gravidade']],
                'unidade' => $alerta->unit->name,
                'descricao' => $alerta->item_nome ? '· '.$alerta->item_nome : '',
                'categoria' => $alerta->texto('titulo'),
                'tempo' => $alerta->tempoRelativo(),
            ]);
    }

    /**
     * @param  Collection<int, string>  $cores
     * @return Collection<int, array{data: mixed, subempresa: string, cor: string, item: string, tipo: string, quantidade: int}>
     */
    private function ultimasMovimentacoes(Collection $cores): Collection
    {
        return StockMovement::with(['item', 'unit'])
            ->latest('movement_date')
            ->latest('id')
            ->take(5)
            ->get()
            ->map(fn (StockMovement $movimentacao) => [
                'data' => $movimentacao->movement_date,
                'subempresa' => $movimentacao->unit->name,
                'cor' => $cores[$movimentacao->unit_id] ?? 'gray',
                'item' => $movimentacao->item->name,
                'tipo' => $movimentacao->type === 'Entrada' ? 'entrada' : 'saida',
                'quantidade' => $movimentacao->quantity,
            ]);
    }

    /**
     * @param  Collection<int, string>  $cores
     * @return Collection<int, array{subempresa: string, cor: string, data: mixed, status: string, status_texto: string}>
     */
    private function pedidosEmAberto(Collection $cores): Collection
    {
        return PedidoReposicao::with('unit')
            ->where('status', 'Em aberto')
            ->latest('data_pedido')
            ->latest('id')
            ->take(5)
            ->get()
            ->map(fn (PedidoReposicao $pedido) => [
                'subempresa' => $pedido->unit?->name ?? $pedido->unidade,
                'cor' => $cores[$pedido->unit_id] ?? 'gray',
                'data' => $pedido->data_pedido,
                'status' => 'pending',
                'status_texto' => $pedido->status,
            ]);
    }
}
