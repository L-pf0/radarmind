<?php

namespace App\Support\Relatorios;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV do relatório, organizado em blocos para ler direto no Excel:
 *
 *   cabeçalho (título, data, quem gerou e filtros)
 *   RESUMO (indicadores)
 *   uma tabela por seção, cada uma com título, explicação, colunas e linha de total
 *
 * Separador ";" e UTF-8 com BOM: o Excel em português abre com acentos e colunas certas.
 */
class ExportadorCsv
{
    private const SEPARADOR = ';';

    public function baixar(Relatorio $relatorio): StreamedResponse
    {
        return response()->streamDownload(function () use ($relatorio) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");

            foreach ($this->linhas($relatorio) as $linha) {
                fputcsv($saida, $linha, self::SEPARADOR, '"', '', "\r\n");
            }

            fclose($saida);
        }, $relatorio->nomeDoArquivo('csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<list<string|int>>
     */
    public function linhas(Relatorio $relatorio): array
    {
        $linhas = [
            ['RadarMind · Gerência do Agronegócio'],
            [mb_strtoupper($relatorio->titulo)],
            [$relatorio->descricao],
            [],
            ['Gerado em', $relatorio->geradoEm->format('d/m/Y H:i')],
            ['Gerado por', SecaoDoRelatorio::textoSeguro($relatorio->geradoPor)],
        ];

        foreach ($relatorio->parametros as $rotulo => $valor) {
            $linhas[] = [$rotulo, $valor];
        }

        $linhas[] = [];
        $linhas[] = ['RESUMO'];
        $linhas[] = ['Indicador', 'Valor', 'Detalhe'];
        foreach ($relatorio->resumo as $indicador) {
            $linhas[] = [$indicador['rotulo'], SecaoDoRelatorio::textoSeguro((string) $indicador['valor']), $indicador['detalhe']];
        }

        foreach ($relatorio->secoes as $secao) {
            array_push($linhas, ...$this->secao($secao));
        }

        return $linhas;
    }

    /**
     * @return list<list<string|int>>
     */
    private function secao(SecaoDoRelatorio $secao): array
    {
        $linhas = [
            [],
            [mb_strtoupper($secao->titulo).' ('.$secao->quantidade().' '.($secao->quantidade() === 1 ? 'registro' : 'registros').')'],
        ];

        if ($secao->descricao !== '') {
            $linhas[] = [$secao->descricao];
        }

        if ($secao->quantidade() === 0) {
            $linhas[] = [$secao->vazio];

            return $linhas;
        }

        $linhas[] = array_keys($secao->colunas);

        foreach ($secao->linhas as $linha) {
            $linhas[] = $this->celulas($secao, $linha);
        }

        if ($secao->temTotal()) {
            $linhas[] = $this->celulas($secao, $secao->total);
        }

        return $linhas;
    }

    /**
     * @param  array<string, string|int|float|null>  $linha
     * @return list<string|int>
     */
    private function celulas(SecaoDoRelatorio $secao, array $linha): array
    {
        $celulas = [];
        foreach ($secao->colunas as $rotulo => $tipo) {
            $celulas[] = SecaoDoRelatorio::paraCsv($linha[$rotulo] ?? null, $tipo);
        }

        return $celulas;
    }
}
