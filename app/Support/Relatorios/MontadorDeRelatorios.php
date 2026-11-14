<?php

namespace App\Support\Relatorios;

use App\Models\Os;
use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\StockMovement;
use App\Models\RadarMind\Unit;
use App\Models\User;
use App\Support\MedidorDeDesempenho;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Monta os relatórios de Estoque, Movimentações e Desempenho.
 *
 * Sem unidade escolhida o relatório é consolidado (todas as unidades, com a coluna "Unidade");
 * com unidade, mostra só ela. O período vale apenas para as movimentações.
 */
class MontadorDeRelatorios
{
    private const ORDEM_DA_SITUACAO = ['Crítico' => 0, 'Baixo' => 1, 'Normal' => 2];

    public function __construct(private MedidorDeDesempenho $medidor) {}

    public function montar(string $tipo, ?Unit $unidade, ?CarbonInterface $inicio, ?CarbonInterface $fim, User $usuario): Relatorio
    {
        return match ($tipo) {
            'estoque' => $this->estoque($unidade, $usuario),
            'movimentacoes' => $this->movimentacoes($unidade, $inicio, $fim, $usuario),
            'desempenho' => $this->desempenho($unidade, $usuario),
        };
    }

    // ===================================================================== Estoque

    private function estoque(?Unit $unidade, User $usuario): Relatorio
    {
        $itens = Item::with('unit')
            ->when($unidade, fn ($query) => $query->where('unit_id', $unidade->id))
            ->orderBy('unit_id')
            ->orderBy('sku')
            ->get();

        $pecas = $itens->where('category', 'peca');
        $maquinas = $itens->where('category', 'maq');

        // Itens fora de uso (Inativo) não entram no alerta, igual à Central de Alertas
        $emAlerta = $itens
            ->filter(fn (Item $item) => $item->status !== 'Inativo' && $item->alert_level !== 'Normal')
            ->sortBy([
                fn (Item $a, Item $b) => self::ORDEM_DA_SITUACAO[$a->alert_level] <=> self::ORDEM_DA_SITUACAO[$b->alert_level],
                fn (Item $a, Item $b) => $a->unit_id <=> $b->unit_id,
                fn (Item $a, Item $b) => $a->sku <=> $b->sku,
            ])
            ->values();

        $reposicao = new SecaoDoRelatorio(
            'Itens que precisam de reposição',
            'Itens abaixo do mínimo (crítico) ou perto dele (baixo). "Repor" é a quantidade que tira o item do alerta.',
            [
                'Código' => 'codigo', 'Item' => 'texto', 'Unidade' => 'texto', 'Em estoque' => 'numero', 'Mínimo' => 'numero',
                'Repor' => 'numero', 'Situação' => 'etiqueta', 'Giro' => 'etiqueta', 'Prioridade' => 'etiqueta',
            ],
            $emAlerta->map(fn (Item $item) => [
                'Código' => $item->sku,
                'Item' => $item->name,
                'Unidade' => $item->unit->name,
                'Em estoque' => $item->quantity,
                'Mínimo' => $item->minimum_quantity,
                'Repor' => $item->quantidadeParaNormalizar(),
                'Situação' => $item->alert_level,
                'Giro' => $item->turnover,
                'Prioridade' => $item->priority,
            ])->all(),
            ['Código' => 'Total a repor', 'Repor' => $emAlerta->sum(fn (Item $item) => $item->quantidadeParaNormalizar())],
            'Nenhum item precisa de reposição: todo o estoque está acima do mínimo.',
        );

        $secaoDePecas = new SecaoDoRelatorio(
            'Peças avulsas',
            'Saldo de cada peça, onde fica guardada e a situação em relação ao mínimo.',
            [
                'Código' => 'codigo', 'Peça' => 'texto', 'Unidade' => 'texto', 'Localização' => 'texto', 'Lote / nº de série' => 'texto',
                'Em estoque' => 'numero', 'Mínimo' => 'numero', 'Situação' => 'etiqueta', 'Giro' => 'etiqueta', 'Status' => 'etiqueta',
            ],
            $pecas->map(fn (Item $item) => [
                'Código' => $item->sku,
                'Peça' => $item->name,
                'Unidade' => $item->unit->name,
                'Localização' => $item->location,
                'Lote / nº de série' => collect([$item->lot, $item->serial_number ? 'Série '.$item->serial_number : null])->filter()->implode(' · ') ?: null,
                'Em estoque' => $item->quantity,
                'Mínimo' => $item->minimum_quantity,
                'Situação' => $this->situacao($item),
                'Giro' => $item->turnover,
                'Status' => $item->status,
            ])->values()->all(),
            ['Código' => 'Total', 'Em estoque' => $pecas->sum('quantity')],
            'Nenhuma peça cadastrada.',
        );

        $secaoDeMaquinas = new SecaoDoRelatorio(
            'Maquinário',
            'Frota de máquinas e implementos. Implementos não têm horímetro.',
            [
                'Código' => 'codigo', 'Máquina' => 'texto', 'Unidade' => 'texto', 'Tipo' => 'texto', 'Modelo / aplicação' => 'texto',
                'Ano' => 'texto', 'Horímetro (h)' => 'decimal', 'Nº de série' => 'texto', 'Status' => 'etiqueta',
            ],
            $maquinas->map(fn (Item $item) => [
                'Código' => $item->sku,
                'Máquina' => $item->name,
                'Unidade' => $item->unit->name,
                'Tipo' => $item->machine_type,
                'Modelo / aplicação' => $item->model_application,
                'Ano' => $item->year,
                'Horímetro (h)' => $item->hour_meter,
                'Nº de série' => $item->serial_number,
                'Status' => $item->status,
            ])->values()->all(),
            null,
            'Nenhum maquinário cadastrado.',
        );

        $criticos = $emAlerta->where('alert_level', 'Crítico')->count();

        return $this->relatorio(
            'estoque',
            'Relatório de estoque',
            'Saldo de peças e maquinário, itens que precisam de reposição e dados de cada item.',
            $unidade,
            [],
            [
                ['rotulo' => 'Itens cadastrados', 'valor' => $itens->count(),
                    'detalhe' => $this->plural($pecas->count(), 'peça avulsa', 'peças avulsas').' · '.$this->plural($maquinas->count(), 'maquinário', 'maquinários')],
                ['rotulo' => 'Peças em estoque', 'valor' => $pecas->sum('quantity'), 'detalhe' => 'unidades somadas de todas as peças'],
                ['rotulo' => 'Precisam de reposição', 'valor' => $emAlerta->count(),
                    'detalhe' => $this->plural($criticos, 'crítico', 'críticos').' · '.$this->plural($emAlerta->count() - $criticos, 'baixo', 'baixos'),
                    'tom' => $emAlerta->isEmpty() ? 'ok' : 'alerta'],
                ['rotulo' => 'Maquinário', 'valor' => $maquinas->count(),
                    'detalhe' => $this->plural($maquinas->where('status', 'Ativo')->count(), 'ativo', 'ativos').' · '
                        .$maquinas->where('status', 'Em manutenção')->count().' em manutenção · '
                        .$this->plural($maquinas->where('status', 'Inativo')->count(), 'inativo', 'inativos')],
            ],
            [$reposicao, $secaoDePecas, $secaoDeMaquinas],
            $usuario,
        );
    }

    private function situacao(Item $item): string
    {
        return $item->status === 'Inativo' ? 'Fora de uso' : $item->alert_level;
    }

    // ===================================================================== Movimentações

    private function movimentacoes(?Unit $unidade, ?CarbonInterface $inicio, ?CarbonInterface $fim, User $usuario): Relatorio
    {
        $movimentacoes = StockMovement::with(['item', 'unit', 'responsavel'])
            ->when($unidade, fn ($query) => $query->where('unit_id', $unidade->id))
            ->when($inicio, fn ($query) => $query->whereDate('movement_date', '>=', $inicio->toDateString()))
            ->when($fim, fn ($query) => $query->whereDate('movement_date', '<=', $fim->toDateString()))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->get();

        $entradas = $movimentacoes->where('type', 'Entrada');
        $saidas = $movimentacoes->where('type', 'Saída');
        $totais = fn (Collection $grupo) => [
            'Registros' => $grupo->count(),
            'Entradas (un.)' => $grupo->where('type', 'Entrada')->sum('quantity'),
            'Saídas (un.)' => $grupo->where('type', 'Saída')->sum('quantity'),
            'Saldo (un.)' => $grupo->where('type', 'Entrada')->sum('quantity') - $grupo->where('type', 'Saída')->sum('quantity'),
        ];
        $colunasDeTotais = ['Registros' => 'numero', 'Entradas (un.)' => 'numero', 'Saídas (un.)' => 'numero', 'Saldo (un.)' => 'saldo'];
        $totalGeral = $totais($movimentacoes);

        $secoes = [];

        // Por unidade: só faz sentido no consolidado
        if ($unidade === null) {
            $secoes[] = new SecaoDoRelatorio(
                'Resumo por unidade',
                'Quanto cada unidade movimentou no período.',
                ['Unidade' => 'texto'] + $colunasDeTotais,
                $movimentacoes->groupBy('unit_id')
                    ->map(fn (Collection $grupo) => ['Unidade' => $grupo->first()->unit->name] + $totais($grupo))
                    ->sortBy('Unidade')
                    ->values()
                    ->all(),
                ['Unidade' => 'Total'] + $totalGeral,
                'Nenhuma movimentação no período.',
            );
        }

        $secoes[] = new SecaoDoRelatorio(
            'Totais por item',
            'Entradas, saídas e saldo de cada item no período, com o estoque atual para comparação.',
            ['Código' => 'codigo', 'Item' => 'texto', 'Unidade' => 'texto'] + $colunasDeTotais + ['Estoque atual' => 'numero'],
            $movimentacoes->groupBy('item_id')
                ->map(function (Collection $grupo) use ($totais) {
                    $item = $grupo->first()->item;

                    return ['Código' => $item->sku, 'Item' => $item->name, 'Unidade' => $grupo->first()->unit->name]
                        + $totais($grupo)
                        + ['Estoque atual' => $item->quantity];
                })
                ->sortBy([['Registros', 'desc'], ['Código', 'asc']])
                ->values()
                ->all(),
            ['Código' => 'Total'] + $totalGeral,
            'Nenhuma movimentação no período.',
        );

        $secoes[] = new SecaoDoRelatorio(
            'Movimentações registradas',
            'Todas as entradas e saídas, da mais recente para a mais antiga.',
            [
                'Data' => 'texto', 'Unidade' => 'texto', 'Código' => 'codigo', 'Item' => 'texto', 'Tipo' => 'etiqueta',
                'Quantidade' => 'numero', 'Responsável' => 'texto', 'Observações' => 'texto',
            ],
            $movimentacoes->map(fn (StockMovement $movimentacao) => [
                'Data' => $movimentacao->movement_date->format('d/m/Y'),
                'Unidade' => $movimentacao->unit->name,
                'Código' => $movimentacao->item->sku,
                'Item' => $movimentacao->item->name,
                'Tipo' => $movimentacao->type,
                'Quantidade' => $movimentacao->quantity,
                'Responsável' => $movimentacao->responsavel?->name,
                'Observações' => $movimentacao->notes,
            ])->all(),
            null,
            'Nenhuma movimentação no período.',
        );

        $saldo = $totalGeral['Saldo (un.)'];

        return $this->relatorio(
            'movimentacoes',
            'Relatório de movimentações',
            'Entradas e saídas de estoque no período, com totais por unidade e por item.',
            $unidade,
            ['Período' => $this->periodo($inicio, $fim)],
            [
                ['rotulo' => 'Movimentações', 'valor' => $movimentacoes->count(),
                    'detalhe' => $this->plural($movimentacoes->pluck('item_id')->unique()->count(), 'item diferente', 'itens diferentes')],
                ['rotulo' => 'Entradas', 'valor' => $entradas->sum('quantity'), 'detalhe' => 'unidades em '.$this->plural($entradas->count(), 'registro', 'registros'), 'tom' => 'ok'],
                ['rotulo' => 'Saídas', 'valor' => $saidas->sum('quantity'), 'detalhe' => 'unidades em '.$this->plural($saidas->count(), 'registro', 'registros')],
                ['rotulo' => 'Saldo do período', 'valor' => SecaoDoRelatorio::paraTela($saldo, 'saldo'), 'detalhe' => 'entradas menos saídas', 'tom' => $saldo < 0 ? 'alerta' : 'ok'],
            ],
            $secoes,
            $usuario,
        );
    }

    private function periodo(?CarbonInterface $inicio, ?CarbonInterface $fim): string
    {
        return match (true) {
            $inicio !== null && $fim !== null => $inicio->format('d/m/Y').' a '.$fim->format('d/m/Y'),
            $inicio !== null => 'A partir de '.$inicio->format('d/m/Y'),
            $fim !== null => 'Até '.$fim->format('d/m/Y'),
            default => 'Todo o período',
        };
    }

    // ===================================================================== Desempenho

    private function desempenho(?Unit $unidade, User $usuario): Relatorio
    {
        // Ranking sempre entre todas as unidades: a posição de uma unidade não muda quando ela é filtrada
        $ranking = $this->medidor->medir(Unit::orderBy('id')->get())
            ->sortByDesc('pontuacao')
            ->values()
            ->map(fn (array $desempenho, int $indice) => $desempenho + ['posicao' => $indice + 1]);

        if ($unidade !== null) {
            $ranking = $ranking->filter(fn (array $desempenho) => $desempenho['unidade']->is($unidade))->values();
        }

        $idsDasUnidades = $ranking->map(fn (array $desempenho) => $desempenho['unidade']->id);
        $itens = Item::whereIn('unit_id', $idsDasUnidades)->get()->groupBy('unit_id');
        $osEmAberto = Os::whereIn('unit_id', $idsDasUnidades)->whereIn('status', ['Aguardando autorização', 'Autorizada'])
            ->selectRaw('unit_id, count(*) as total')->groupBy('unit_id')->pluck('total', 'unit_id');
        $pedidosEmAberto = PedidoReposicao::whereIn('unit_id', $idsDasUnidades)->where('status', 'Em aberto')
            ->selectRaw('unit_id, count(*) as total')->groupBy('unit_id')->pluck('total', 'unit_id');

        $rankingSecao = new SecaoDoRelatorio(
            'Ranking de desempenho',
            'Pontuação de 0 a 100: até 50 pontos por movimentações registradas e até 50 por itens de alto giro em estoque, comparando cada unidade com a líder de cada critério.',
            [
                'Posição' => 'texto', 'Unidade' => 'texto', 'Desempenho (%)' => 'numero',
                'Pontos: movimentações (até 50)' => 'numero', 'Movimentações registradas' => 'numero',
                'Pontos: alto giro (até 50)' => 'numero', 'Itens de alto giro em estoque' => 'numero',
            ],
            $ranking->map(fn (array $desempenho) => [
                'Posição' => $desempenho['posicao'].'º',
                'Unidade' => $desempenho['unidade']->name,
                'Desempenho (%)' => $desempenho['pontuacao'],
                'Pontos: movimentações (até 50)' => $desempenho['pontos_movimentacoes'],
                'Movimentações registradas' => $desempenho['movimentacoes'],
                'Pontos: alto giro (até 50)' => $desempenho['pontos_alto_giro'],
                'Itens de alto giro em estoque' => $desempenho['itens_alto_giro'],
            ])->all(),
            null,
            'Nenhuma unidade cadastrada.',
        );

        $operacao = $ranking->map(function (array $desempenho) use ($itens, $osEmAberto, $pedidosEmAberto) {
            $unidadeId = $desempenho['unidade']->id;
            $itensDaUnidade = $itens->get($unidadeId, collect());
            $ativos = $itensDaUnidade->where('status', '!=', 'Inativo');

            return [
                'Unidade' => $desempenho['unidade']->name,
                'Itens cadastrados' => $itensDaUnidade->count(),
                'Peças em estoque (un.)' => $itensDaUnidade->where('category', 'peca')->sum('quantity'),
                'Itens em alerta' => $ativos->filter(fn (Item $item) => $item->alert_level !== 'Normal')->count(),
                'Máquinas em manutenção' => $itensDaUnidade->where('category', 'maq')->where('status', 'Em manutenção')->count(),
                'O.S. em aberto' => (int) ($osEmAberto[$unidadeId] ?? 0),
                'Pedidos de reposição em aberto' => (int) ($pedidosEmAberto[$unidadeId] ?? 0),
            ];
        });

        $colunasDaOperacao = [
            'Unidade' => 'texto', 'Itens cadastrados' => 'numero', 'Peças em estoque (un.)' => 'numero', 'Itens em alerta' => 'numero',
            'Máquinas em manutenção' => 'numero', 'O.S. em aberto' => 'numero', 'Pedidos de reposição em aberto' => 'numero',
        ];

        $operacaoSecao = new SecaoDoRelatorio(
            'Situação operacional',
            'O que cada unidade tem pendente hoje: itens em alerta, máquinas paradas, O.S. e pedidos de reposição em aberto.',
            $colunasDaOperacao,
            $operacao->all(),
            // soma pela chave exata: o "." de "(un.)" seria lido como caminho por $operacao->sum('...')
            ['Unidade' => 'Total'] + collect($colunasDaOperacao)->except('Unidade')
                ->map(fn ($tipo, string $coluna) => $operacao->sum(fn (array $linha) => $linha[$coluna]))->all(),
            'Nenhuma unidade cadastrada.',
        );

        $lider = $ranking->first();
        $pendencias = $operacao->sum('Itens em alerta') + $operacao->sum('O.S. em aberto') + $operacao->sum('Pedidos de reposição em aberto');

        return $this->relatorio(
            'desempenho',
            'Relatório de desempenho',
            'Comparativo entre as unidades: pontuação de desempenho e pendências operacionais.',
            $unidade,
            ['Critério' => 'Movimentações registradas (50 pts) + itens de alto giro em estoque (50 pts)'],
            [
                ['rotulo' => 'Unidades avaliadas', 'valor' => $ranking->count(), 'detalhe' => $unidade ? 'unidade escolhida' : 'todas as unidades'],
                ['rotulo' => $unidade ? 'Posição no ranking' : 'Melhor desempenho', 'valor' => $lider ? ($unidade ? $lider['posicao'].'º' : $lider['unidade']->name) : '—',
                    'detalhe' => $lider ? $lider['pontuacao'].'% de desempenho' : 'sem dados', 'tom' => 'ok'],
                ['rotulo' => 'Movimentações registradas', 'valor' => $ranking->sum('movimentacoes'), 'detalhe' => 'desde o início do sistema'],
                ['rotulo' => 'Pendências', 'valor' => $pendencias, 'detalhe' => 'itens em alerta, O.S. e pedidos em aberto', 'tom' => $pendencias > 0 ? 'alerta' : 'ok'],
            ],
            [$rankingSecao, $operacaoSecao],
            $usuario,
        );
    }

    // ===================================================================== Comum

    private function plural(int $quantidade, string $singular, string $plural): string
    {
        return $quantidade.' '.($quantidade === 1 ? $singular : $plural);
    }

    /**
     * Junta as partes e tira a coluna "Unidade" quando o relatório é de uma unidade só.
     *
     * @param  array<string, string>  $parametrosExtras
     * @param  list<array{rotulo: string, valor: string|int, detalhe: string, tom?: string}>  $resumo
     * @param  list<SecaoDoRelatorio>  $secoes
     */
    private function relatorio(string $tipo, string $titulo, string $descricao, ?Unit $unidade, array $parametrosExtras, array $resumo, array $secoes, User $usuario): Relatorio
    {
        if ($unidade !== null) {
            $secoes = array_map(fn (SecaoDoRelatorio $secao) => $secao->semColunas(['Unidade']), $secoes);
        }

        return new Relatorio(
            $tipo,
            $titulo,
            $descricao,
            ['Unidade' => $unidade?->name ?? 'Todas as unidades (consolidado)'] + $parametrosExtras,
            $resumo,
            $secoes,
            CarbonImmutable::now(),
            $usuario->name,
            $unidade?->name ?? 'consolidado',
        );
    }
}
