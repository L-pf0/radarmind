<?php

namespace App\Support;

use App\Models\RadarMind\Item;
use App\Models\RadarMind\StockMovement;
use App\Models\RadarMind\Unit;
use Illuminate\Support\Collection;

/**
 * Mede o desempenho de cada unidade (0 a 100) por dois critérios de peso igual:
 * - Movimentações: quantas movimentações de estoque a unidade já registrou (quanto mais, melhor).
 * - Alto giro: quantos itens de alto giro a unidade tem em estoque (indicam boa margem de vendas).
 *
 * Cada critério é comparado com a unidade que mais se destaca nele: quem lidera um critério
 * ganha os 50 pontos dele, e as demais ganham pontos proporcionais.
 */
class MedidorDeDesempenho
{
    public const PESO_MOVIMENTACOES = 50;

    public const PESO_ALTO_GIRO = 50;

    /**
     * @param  Collection<int, Unit>|null  $unidades  Unidades a medir (padrão: todas)
     * @return Collection<int, array{unidade: Unit, movimentacoes: int, itens_alto_giro: int, pontos_movimentacoes: int, pontos_alto_giro: int, pontuacao: int}>
     */
    public function medir(?Collection $unidades = null): Collection
    {
        $unidades ??= Unit::orderBy('id')->get();

        // Os líderes de cada critério são sempre calculados entre TODAS as unidades,
        // para a pontuação de uma unidade não mudar quando o relatório filtra só ela.
        $movimentacoesPorUnidade = StockMovement::query()
            ->selectRaw('unit_id, count(*) as total')
            ->groupBy('unit_id')
            ->pluck('total', 'unit_id');

        $altoGiroPorUnidade = Item::query()
            ->where('turnover', 'Alto giro')
            ->where('status', '!=', 'Inativo')
            ->where('quantity', '>', 0)
            ->selectRaw('unit_id, count(*) as total')
            ->groupBy('unit_id')
            ->pluck('total', 'unit_id');

        $maiorMovimentacoes = (int) $movimentacoesPorUnidade->max();
        $maiorAltoGiro = (int) $altoGiroPorUnidade->max();

        return $unidades->values()->map(function (Unit $unidade) use ($movimentacoesPorUnidade, $altoGiroPorUnidade, $maiorMovimentacoes, $maiorAltoGiro) {
            $movimentacoes = (int) ($movimentacoesPorUnidade[$unidade->id] ?? 0);
            $itensAltoGiro = (int) ($altoGiroPorUnidade[$unidade->id] ?? 0);

            $pontosMovimentacoes = $this->proporcional($movimentacoes, $maiorMovimentacoes, self::PESO_MOVIMENTACOES);
            $pontosAltoGiro = $this->proporcional($itensAltoGiro, $maiorAltoGiro, self::PESO_ALTO_GIRO);

            return [
                'unidade' => $unidade,
                'movimentacoes' => $movimentacoes,
                'itens_alto_giro' => $itensAltoGiro,
                'pontos_movimentacoes' => (int) round($pontosMovimentacoes),
                'pontos_alto_giro' => (int) round($pontosAltoGiro),
                'pontuacao' => (int) round($pontosMovimentacoes + $pontosAltoGiro),
            ];
        });
    }

    /**
     * @return array{unidade: Unit, movimentacoes: int, itens_alto_giro: int, pontos_movimentacoes: int, pontos_alto_giro: int, pontuacao: int}
     */
    public function medirUnidade(Unit $unidade): array
    {
        return $this->medir(collect([$unidade]))->first();
    }

    private function proporcional(int $valor, int $maior, int $peso): float
    {
        return $maior > 0 ? $valor / $maior * $peso : 0;
    }
}
