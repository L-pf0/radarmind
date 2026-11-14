<?php

namespace App\Support\Relatorios;

/**
 * Uma tabela do relatório (ex.: "Peças avulsas", "Totais por item").
 *
 * As colunas dizem o tipo de cada valor, e cada saída formata do seu jeito:
 * a tela mostra "3.931" e etiquetas coloridas; o CSV grava 3931 para o Excel somar.
 * Tipos: texto, codigo, numero, decimal, saldo (com sinal + ou −) e etiqueta.
 */
final class SecaoDoRelatorio
{
    /**
     * @param  array<string, string>  $colunas  rótulo => tipo
     * @param  list<array<string, string|int|float|null>>  $linhas  cada linha indexada pelo rótulo da coluna
     * @param  array<string, string|int|float|null>|null  $total  linha de total (só as colunas que somam)
     */
    public function __construct(
        public readonly string $titulo,
        public readonly string $descricao,
        public readonly array $colunas,
        public readonly array $linhas,
        public readonly ?array $total = null,
        public readonly string $vazio = 'Nenhum registro encontrado para os parâmetros escolhidos.',
    ) {}

    /**
     * Cópia da seção sem algumas colunas (ex.: "Unidade" quando o relatório é de uma unidade só).
     *
     * @param  list<string>  $rotulos
     */
    public function semColunas(array $rotulos): self
    {
        $remover = array_flip($rotulos);

        return new self(
            $this->titulo,
            $this->descricao,
            array_diff_key($this->colunas, $remover),
            array_map(fn (array $linha) => array_diff_key($linha, $remover), $this->linhas),
            $this->total === null ? null : array_diff_key($this->total, $remover),
            $this->vazio,
        );
    }

    public function quantidade(): int
    {
        return count($this->linhas);
    }

    /**
     * Linha de total só quando soma mais de uma linha (com uma só, repetiria a própria linha).
     */
    public function temTotal(): bool
    {
        return $this->total !== null && $this->quantidade() > 1;
    }

    public static function ehNumerica(string $tipo): bool
    {
        return in_array($tipo, ['numero', 'decimal', 'saldo'], true);
    }

    /**
     * Valor como aparece na tela e no PDF.
     */
    public static function paraTela(string|int|float|null $valor, string $tipo): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($tipo) {
            'numero' => number_format((float) $valor, 0, ',', '.'),
            'decimal' => number_format((float) $valor, 1, ',', '.'),
            'saldo' => ($valor > 0 ? '+' : ($valor < 0 ? '−' : '')).number_format(abs((float) $valor), 0, ',', '.'),
            default => (string) $valor,
        };
    }

    /**
     * Valor gravado no CSV: números sem separador de milhar (o Excel reconhece e soma),
     * decimais com vírgula e textos protegidos contra fórmulas.
     */
    public static function paraCsv(string|int|float|null $valor, string $tipo): string|int
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return match ($tipo) {
            'numero', 'saldo' => (int) $valor,
            'decimal' => number_format((float) $valor, 1, ',', ''),
            default => self::textoSeguro((string) $valor),
        };
    }

    /**
     * Cor da etiqueta (classes .badge do módulo) conforme o valor.
     */
    public static function classeDaEtiqueta(string|int|float|null $valor): string
    {
        return match ((string) $valor) {
            'Crítico', 'Urgente' => 'bad',
            'Baixo', 'Em manutenção', 'Alta' => 'low',
            'Normal', 'Ativo', 'Entrada' => 'ok',
            'Saída', 'Médio giro', 'Média' => 'off',
            'Alto giro' => 'hot',
            default => 'mute',
        };
    }

    /**
     * Texto que começa com = + - @ seria lido como fórmula pelo Excel.
     */
    public static function textoSeguro(string $texto): string
    {
        return preg_match('/^[=+\-@\t\r]/', $texto) === 1 ? "'".$texto : $texto;
    }
}
