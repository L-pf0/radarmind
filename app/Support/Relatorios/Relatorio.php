<?php

namespace App\Support\Relatorios;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Relatório pronto para mostrar na tela/PDF ou baixar em CSV (as duas saídas usam este mesmo objeto).
 */
final class Relatorio
{
    /**
     * @param  array<string, string>  $parametros  filtros usados (Unidade, Período...)
     * @param  list<array{rotulo: string, valor: string|int, detalhe: string, tom?: string}>  $resumo  indicadores do topo
     * @param  list<SecaoDoRelatorio>  $secoes
     */
    public function __construct(
        public readonly string $tipo,
        public readonly string $titulo,
        public readonly string $descricao,
        public readonly array $parametros,
        public readonly array $resumo,
        public readonly array $secoes,
        public readonly CarbonInterface $geradoEm,
        public readonly string $geradoPor,
        public readonly string $escopo = 'consolidado',
    ) {}

    /**
     * Ex.: relatorio-estoque-subempresa-01-2026-11-14-1032.csv
     */
    public function nomeDoArquivo(string $extensao = 'csv'): string
    {
        return 'relatorio-'.$this->tipo.'-'.Str::slug($this->escopo).'-'.$this->geradoEm->format('Y-m-d-Hi').'.'.$extensao;
    }
}
