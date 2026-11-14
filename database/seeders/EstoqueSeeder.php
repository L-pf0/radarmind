<?php

namespace Database\Seeders;

use App\Models\RadarMind\Item;
use App\Models\RadarMind\Unit;
use Illuminate\Database\Seeder;

/**
 * Estoque de demonstração com itens reais do agronegócio.
 *
 * - Matriz: 80 peças avulsas + 20 maquinários (frota própria).
 * - Subempresa 01 (grãos): 60 peças + 10 maquinários do mesmo catálogo da matriz, com
 *   quantidades menores, o próprio almoxarifado e os lotes recebidos da matriz.
 * - Subempresas 02 a 04: montadas pelo tipo de operação de cada uma (PERFIS_DAS_FILIAIS):
 *   pulverização, preparo de solo/plantio e colheita.
 *   O código de cada filial é o da matriz com "-S01", "-S02"... (o código/SKU é único no sistema).
 *
 * Alguns itens já começam abaixo do mínimo (nível baixo/crítico) e duas máquinas estão em
 * manutenção: rodando com os eventos do model ligados, os alertas correspondentes são criados.
 * Pode rodar mais de uma vez sem duplicar (updateOrCreate pelo código).
 */
class EstoqueSeeder extends Seeder
{
    /** Sufixo do código dos itens da filial. */
    private const SUFIXO_FILIAL = '-S01';

    /** Onde cada setor do armazém da matriz fica no almoxarifado da filial. */
    private const LOCAIS_DA_FILIAL = [
        'Galpão A' => 'Almoxarifado',
        'Galpão B' => 'Almoxarifado',
        'Sala elétrica' => 'Almoxarifado · Armário elétrico',
        'Depósito de lubrificantes' => 'Casa de óleo',
        'Pátio coberto' => 'Barracão de máquinas',
    ];

    public function run(): void
    {
        $matriz = Unit::where('code', 'MAT')->firstOrFail();
        $filial = Unit::where('code', 'SUB01')->firstOrFail();

        foreach ($this->pecas() as $peca) {
            $this->salvar($matriz, $peca['sku'], [
                'category' => 'peca',
                'name' => $peca['nome'],
                'location' => $peca['local'],
                'lot' => $peca['lote'],
                'serial_number' => $this->numeroDeSerie($this->serieDaPeca($peca), $peca['qtd']),
                'quantity' => $peca['qtd'],
                'minimum_quantity' => $peca['min'],
                'status' => $peca['status'] ?? 'Ativo',
                'turnover' => $peca['giro'],
            ]);

            if ($peca['filial'] !== null) {
                [$quantidade, $minimo] = $peca['filial'];

                $this->salvar($filial, $peca['sku'].self::SUFIXO_FILIAL, [
                    'category' => 'peca',
                    'name' => $peca['nome'],
                    'location' => strtr($peca['local'], self::LOCAIS_DA_FILIAL),
                    'lot' => $peca['lote'], // abastecida com o mesmo lote comprado pela matriz
                    'serial_number' => $this->numeroDeSerie($this->serieDaPeca($peca), $quantidade, 500),
                    'quantity' => $quantidade,
                    'minimum_quantity' => $minimo,
                    'status' => 'Ativo',
                    'turnover' => $peca['giro'],
                ]);
            }
        }

        $vagaNaFilial = 0;

        foreach ($this->maquinas() as $vaga => $maquina) {
            $this->salvar($matriz, $maquina['sku'], $this->dadosDaMaquina($maquina + ['local' => $this->localDaMaquina($maquina['tipo'], $vaga + 1)]));

            if ($maquina['filial'] !== null) {
                $this->salvar($filial, $maquina['sku'].self::SUFIXO_FILIAL, $this->dadosDaMaquina(
                    $maquina['filial'] + ['status' => 'Ativo', 'local' => 'Barracão de máquinas · Vaga '.++$vagaNaFilial] + $maquina,
                ));
            }
        }

        foreach (self::PERFIS_DAS_FILIAIS as $codigo => $perfil) {
            $this->montarFilial(Unit::where('code', $codigo)->firstOrFail(), $codigo, $perfil);
        }
    }

    /**
     * Subempresas 02 a 04: cada uma estoca as famílias de peças e as máquinas do seu tipo de operação,
     * com o mesmo catálogo da matriz (código + "-S02"...), quantidades proporcionais ao tamanho da unidade
     * e alguns itens já em alerta. Os números variam por item de forma fixa (a seed gera sempre o mesmo estoque).
     *
     * @var array<string, array{operacao: string, familias: list<string>, sem: list<string>, fator: float, alertas: array<string, array{0: int, 1: int}>, maquinas: array<string, string>, serie: int}>
     */
    private const PERFIS_DAS_FILIAIS = [
        // Pulverização e tratos culturais: frota de pulverizadores e peças de aplicação
        'SUB02' => [
            'operacao' => 'Pulverização e tratos culturais',
            'familias' => ['filtros', 'correias', 'hidraulica', 'lubrificantes', 'eletrica', 'pulverizacao', 'rodagem', 'motor'],
            'sem' => ['PEC-0113', 'PEC-0114', 'PEC-0116', 'PEC-0125', 'PEC-0144', 'PEC-0146'],
            'fator' => 0.45,
            'alertas' => ['PEC-0157' => [30, 60], 'PEC-0162' => [2, 4], 'PEC-0109' => [12, 10], 'PEC-0130' => [7, 6]],
            'maquinas' => ['MAQ-0001' => 'Ativo', 'MAQ-0003' => 'Ativo', 'MAQ-0011' => 'Em manutenção', 'MAQ-0013' => 'Ativo', 'MAQ-0017' => 'Ativo', 'MAQ-0019' => 'Ativo'],
            'serie' => 1000,
        ],
        // Preparo de solo e plantio: tratores de alta potência, grade e plantadeiras
        'SUB03' => [
            'operacao' => 'Preparo de solo e plantio',
            'familias' => ['filtros', 'correias', 'hidraulica', 'lubrificantes', 'eletrica', 'plantio', 'solo', 'rodagem', 'motor'],
            'sem' => ['PEC-0109', 'PEC-0113', 'PEC-0114'],
            'fator' => 0.5,
            'alertas' => ['PEC-0174' => [6, 12], 'PEC-0172' => [3, 6], 'PEC-0151' => [4, 8], 'PEC-0148' => [9, 8], 'PEC-0104' => [6, 5]],
            'maquinas' => ['MAQ-0002' => 'Ativo', 'MAQ-0005' => 'Em manutenção', 'MAQ-0007' => 'Ativo', 'MAQ-0014' => 'Ativo', 'MAQ-0016' => 'Ativo', 'MAQ-0017' => 'Ativo', 'MAQ-0018' => 'Ativo'],
            'serie' => 1500,
        ],
        // Colheita e transbordo: colheitadeiras, plataforma e carreta graneleira
        'SUB04' => [
            'operacao' => 'Colheita e transbordo de grãos',
            'familias' => ['filtros', 'correias', 'hidraulica', 'lubrificantes', 'eletrica', 'colheita', 'rodagem', 'motor'],
            'sem' => ['PEC-0109', 'PEC-0116', 'PEC-0135', 'PEC-0144', 'PEC-0146'],
            'fator' => 0.4,
            'alertas' => ['PEC-0164' => [40, 80], 'PEC-0166' => [5, 8], 'PEC-0167' => [2, 2], 'PEC-0103' => [10, 8]],
            'maquinas' => ['MAQ-0006' => 'Ativo', 'MAQ-0009' => 'Ativo', 'MAQ-0010' => 'Ativo', 'MAQ-0019' => 'Ativo', 'MAQ-0020' => 'Ativo'],
            'serie' => 2000,
        ],
    ];

    /**
     * @param  array{operacao: string, familias: list<string>, sem: list<string>, fator: float, alertas: array<string, array{0: int, 1: int}>, maquinas: array<string, string>, serie: int}  $perfil
     */
    private function montarFilial(Unit $unidade, string $codigo, array $perfil): void
    {
        $sufixo = '-'.str_replace('SUB', 'S', $codigo);

        foreach ($this->pecas() as $peca) {
            $estoca = ($peca['status'] ?? 'Ativo') !== 'Inativo'
                && in_array($this->familia($peca['sku']), $perfil['familias'], true)
                && ! in_array($peca['sku'], $perfil['sem'], true);

            if (! $estoca) {
                continue;
            }

            [$quantidade, $minimo] = $perfil['alertas'][$peca['sku']] ?? $this->quantidadeSemAlerta($peca, $perfil['fator'], $peca['sku'].$codigo);

            $this->salvar($unidade, $peca['sku'].$sufixo, [
                'category' => 'peca',
                'name' => $peca['nome'],
                'location' => strtr($peca['local'], self::LOCAIS_DA_FILIAL),
                'lot' => $peca['lote'], // abastecida com o lote comprado pela matriz
                'serial_number' => $this->numeroDeSerie($this->serieDaPeca($peca), $quantidade, $perfil['serie']),
                'quantity' => $quantidade,
                'minimum_quantity' => $minimo,
                'status' => 'Ativo',
                'turnover' => $peca['giro'],
            ]);
        }

        $maquinasDaMatriz = collect($this->maquinas())->keyBy('sku');

        $vaga = 0;

        foreach ($perfil['maquinas'] as $sku => $status) {
            $maquina = $maquinasDaMatriz[$sku];
            $chave = $sku.$codigo;
            $ano = min(2025, max(2016, $maquina['ano'] + $this->numero($chave) % 3 - 1));

            // Mesmo modelo, mas outra unidade física: ano, horímetro, série e lote próprios
            $this->salvar($unidade, $sku.$sufixo, $this->dadosDaMaquina([
                'ano' => $ano,
                'horas' => $maquina['horas'] === null ? null : round($maquina['horas'] * $this->variacao($chave, 0.65, 1.2), 1),
                'serie' => substr($maquina['serie'], 0, -4).str_pad((string) ($this->numero($chave) % 10000), 4, '0', STR_PAD_LEFT),
                'lote' => "L{$ano}-".str_pad((string) ($this->numero($chave) % 90 + 10), 3, '0', STR_PAD_LEFT),
                'status' => $status,
                'local' => 'Barracão de máquinas · Vaga '.++$vaga,
            ] + $maquina));
        }
    }

    /**
     * Quantidade e mínimo proporcionais aos da matriz, sempre acima do nível de alerta
     * (os itens em alerta de cada filial são escolhidos no perfil).
     *
     * @param  array{qtd: int, min: int}  $peca
     * @return array{0: int, 1: int}
     */
    private function quantidadeSemAlerta(array $peca, float $fator, string $chave): array
    {
        $minimo = $peca['min'] === 0 ? 0 : max(1, (int) round($peca['min'] * $fator));
        $quantidade = (int) round($peca['qtd'] * $fator * $this->variacao($chave, 0.8, 1.25));

        // Normal = acima de 125% do mínimo (mesma regra do Item::getAlertLevelAttribute)
        return [max($quantidade, (int) ceil($minimo * 1.25) + 1 + $this->numero($chave) % 3), $minimo];
    }

    /**
     * Família da peça pela faixa de código do catálogo da matriz.
     */
    private function familia(string $sku): string
    {
        $numero = (int) substr($sku, -3);

        return match (true) {
            $numero <= 110 => 'filtros',
            $numero <= 120 => 'correias',
            $numero <= 128 => 'hidraulica',
            $numero <= 135 => 'lubrificantes',
            $numero <= 146 => 'eletrica',
            $numero <= 155 => 'plantio',
            $numero <= 163 => 'pulverizacao',
            $numero <= 171 => 'colheita',
            $numero <= 175 => 'solo',
            $numero <= 178 => 'rodagem',
            default => 'motor',
        };
    }

    /** Número fixo derivado do texto (a seed sempre gera os mesmos valores). */
    private function numero(string $chave): int
    {
        return crc32($chave);
    }

    private function variacao(string $chave, float $minimo, float $maximo): float
    {
        return $minimo + ($this->numero($chave) % 1000) / 999 * ($maximo - $minimo);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function salvar(Unit $unidade, string $sku, array $dados): void
    {
        Item::updateOrCreate(['sku' => $sku], $dados + ['unit_id' => $unidade->id]);
    }

    /**
     * Cada máquina é uma unidade física (1 em estoque, com número de série próprio).
     * Implementos (plantadeira, grade, carreta...) não têm horímetro.
     *
     * @param  array<string, mixed>  $maquina
     * @return array<string, mixed>
     */
    private function dadosDaMaquina(array $maquina): array
    {
        return [
            'category' => 'maq',
            'name' => $maquina['nome'],
            'machine_type' => $maquina['tipo'],
            'model_application' => $maquina['modelo'],
            'year' => $maquina['ano'],
            'hour_meter' => $maquina['horas'],
            'serial_number' => $maquina['serie'],
            'lot' => $maquina['lote'],
            'location' => $maquina['local'],
            'quantity' => 1,
            'minimum_quantity' => 0,
            'status' => $maquina['status'] ?? 'Ativo',
            'turnover' => 'Baixo giro',
        ];
    }

    /**
     * Peças com número de série recebem a faixa de séries das unidades em estoque
     * (ex.: "BAT150-2609114 a BAT150-2609121"). A filial usa outra faixa (deslocamento).
     *
     * @param  array{0: string, 1: int}  $serie
     */
    private function numeroDeSerie(array $serie, int $quantidade, int $deslocamento = 0): string
    {
        [$prefixo, $inicio] = $serie;
        $primeiro = $inicio + $deslocamento;

        return $quantidade <= 1
            ? $prefixo.$primeiro
            : "{$prefixo}{$primeiro} a {$prefixo}".($primeiro + $quantidade - 1);
    }

    /**
     * Série da peça: a do fabricante quando existe (baterias, pneus...); nas demais, a faixa de
     * rastreio do lote, "SN" + número do código (ex.: SN0101-1001 a SN0101-1064).
     *
     * @param  array{sku: string, serie?: array{0: string, 1: int}}  $peca
     * @return array{0: string, 1: int}
     */
    private function serieDaPeca(array $peca): array
    {
        return $peca['serie'] ?? ['SN'.substr($peca['sku'], 4).'-', 1001];
    }

    /**
     * Onde cada máquina fica guardada na matriz, conforme o tipo.
     */
    private function localDaMaquina(string $tipo, int $vaga): string
    {
        return match ($tipo) {
            'Trator' => 'Galpão de tratores · Vaga '.$vaga,
            'Colheitadeira' => 'Galpão de colheitadeiras · Vaga '.$vaga,
            'Pulverizador' => 'Galpão de pulverizadores · Vaga '.$vaga,
            default => 'Pátio de implementos · Box '.$vaga,
        };
    }

    /**
     * 80 peças da matriz. "filial" = [quantidade, mínimo] na Subempresa 01 (null = a filial não estoca).
     *
     * @return list<array{sku: string, nome: string, local: string, lote: string, qtd: int, min: int, giro: string, filial: array{0: int, 1: int}|null, serie?: array{0: string, 1: int}, status?: string}>
     */
    private function pecas(): array
    {
        return [
            // ----- Filtros -----
            ['sku' => 'PEC-0101', 'nome' => 'Filtro de óleo do motor (rosca)', 'local' => 'Galpão A · Corredor 1 · Prateleira A1', 'lote' => 'L2026-112', 'qtd' => 64, 'min' => 24, 'giro' => 'Alto giro', 'filial' => [26, 10]],
            ['sku' => 'PEC-0102', 'nome' => 'Filtro de combustível primário', 'local' => 'Galpão A · Corredor 1 · Prateleira A2', 'lote' => 'L2026-113', 'qtd' => 48, 'min' => 20, 'giro' => 'Alto giro', 'filial' => [20, 8]],
            ['sku' => 'PEC-0103', 'nome' => 'Filtro separador de água do diesel', 'local' => 'Galpão A · Corredor 1 · Prateleira A3', 'lote' => 'L2026-098', 'qtd' => 9, 'min' => 20, 'giro' => 'Alto giro', 'filial' => [6, 8]],
            ['sku' => 'PEC-0104', 'nome' => 'Filtro de ar primário (elemento externo)', 'local' => 'Galpão A · Corredor 1 · Prateleira A4', 'lote' => 'L2026-087', 'qtd' => 13, 'min' => 12, 'giro' => 'Alto giro', 'filial' => [10, 5]],
            ['sku' => 'PEC-0105', 'nome' => 'Filtro de ar secundário (elemento de segurança)', 'local' => 'Galpão A · Corredor 1 · Prateleira A5', 'lote' => 'L2026-088', 'qtd' => 30, 'min' => 10, 'giro' => 'Médio giro', 'filial' => [12, 4]],
            ['sku' => 'PEC-0106', 'nome' => 'Filtro hidráulico de retorno', 'local' => 'Galpão A · Corredor 1 · Prateleira B1', 'lote' => 'L2026-104', 'qtd' => 36, 'min' => 12, 'giro' => 'Médio giro', 'filial' => [14, 5]],
            ['sku' => 'PEC-0107', 'nome' => 'Filtro da transmissão', 'local' => 'Galpão A · Corredor 1 · Prateleira B2', 'lote' => 'L2026-076', 'qtd' => 22, 'min' => 8, 'giro' => 'Médio giro', 'filial' => [9, 3]],
            ['sku' => 'PEC-0108', 'nome' => 'Filtro de ar da cabine (carvão ativado)', 'local' => 'Galpão A · Corredor 1 · Prateleira B3', 'lote' => 'L2026-091', 'qtd' => 28, 'min' => 10, 'giro' => 'Médio giro', 'filial' => [11, 4]],
            ['sku' => 'PEC-0109', 'nome' => 'Filtro de linha do pulverizador (malha 50)', 'local' => 'Galpão A · Corredor 1 · Prateleira B4', 'lote' => 'L2026-120', 'qtd' => 70, 'min' => 25, 'giro' => 'Alto giro', 'filial' => [8, 10]],
            ['sku' => 'PEC-0110', 'nome' => 'Filtro hidráulico de sucção (modelo antigo, descontinuado)', 'local' => 'Galpão A · Corredor 1 · Prateleira B5', 'lote' => 'L2023-044', 'qtd' => 4, 'min' => 0, 'giro' => 'Baixo giro', 'status' => 'Inativo', 'filial' => null],

            // ----- Correias, rolamentos e vedação -----
            ['sku' => 'PEC-0111', 'nome' => 'Correia do alternador (perfil A)', 'local' => 'Galpão A · Corredor 2 · Prateleira C1', 'lote' => 'L2026-061', 'qtd' => 26, 'min' => 8, 'giro' => 'Médio giro', 'filial' => [10, 4]],
            ['sku' => 'PEC-0112', 'nome' => 'Correia do ventilador do motor', 'local' => 'Galpão A · Corredor 2 · Prateleira C2', 'lote' => 'L2026-062', 'qtd' => 18, 'min' => 6, 'giro' => 'Médio giro', 'filial' => [7, 3]],
            ['sku' => 'PEC-0113', 'nome' => 'Correia do variador da plataforma de corte', 'local' => 'Galpão A · Corredor 2 · Prateleira C3', 'lote' => 'L2025-210', 'qtd' => 2, 'min' => 4, 'giro' => 'Médio giro', 'filial' => null],
            ['sku' => 'PEC-0114', 'nome' => 'Correia do picador de palha', 'local' => 'Galpão A · Corredor 2 · Prateleira C4', 'lote' => 'L2026-015', 'qtd' => 10, 'min' => 4, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0115', 'nome' => 'Rolamento rígido de esferas 6206-2RS', 'local' => 'Galpão A · Corredor 2 · Prateleira D1', 'lote' => 'L2026-130', 'qtd' => 7, 'min' => 12, 'giro' => 'Alto giro', 'filial' => [7, 6]],
            ['sku' => 'PEC-0116', 'nome' => 'Rolamento 6305-2RS da roda da plantadeira', 'local' => 'Galpão A · Corredor 2 · Prateleira D2', 'lote' => 'L2026-131', 'qtd' => 40, 'min' => 15, 'giro' => 'Alto giro', 'filial' => [18, 8]],
            ['sku' => 'PEC-0117', 'nome' => 'Rolamento cônico do cubo da roda dianteira', 'local' => 'Galpão A · Corredor 2 · Prateleira D3', 'lote' => 'L2026-071', 'qtd' => 12, 'min' => 4, 'giro' => 'Baixo giro', 'filial' => [5, 2]],
            ['sku' => 'PEC-0118', 'nome' => 'Retentor do eixo traseiro', 'local' => 'Galpão A · Corredor 2 · Prateleira D4', 'lote' => 'L2026-072', 'qtd' => 16, 'min' => 6, 'giro' => 'Médio giro', 'filial' => [6, 2]],
            ['sku' => 'PEC-0119', 'nome' => 'Kit de vedação do cilindro hidráulico 80 mm', 'local' => 'Galpão A · Corredor 2 · Prateleira D5', 'lote' => 'L2026-093', 'qtd' => 14, 'min' => 5, 'giro' => 'Médio giro', 'filial' => [6, 2]],
            ['sku' => 'PEC-0120', 'nome' => 'Cruzeta do eixo cardã', 'local' => 'Galpão A · Corredor 2 · Prateleira D6', 'lote' => 'L2026-055', 'qtd' => 15, 'min' => 6, 'giro' => 'Médio giro', 'filial' => [6, 2]],

            // ----- Hidráulica -----
            ['sku' => 'PEC-0121', 'nome' => 'Mangueira hidráulica 1/2" 2 tramas (1,5 m prensada)', 'local' => 'Galpão A · Corredor 3 · Prateleira E1', 'lote' => 'L2026-140', 'qtd' => 45, 'min' => 15, 'giro' => 'Alto giro', 'filial' => [16, 6]],
            ['sku' => 'PEC-0122', 'nome' => 'Mangueira hidráulica 3/4" 2 tramas (2 m prensada)', 'local' => 'Galpão A · Corredor 3 · Prateleira E2', 'lote' => 'L2026-141', 'qtd' => 24, 'min' => 8, 'giro' => 'Médio giro', 'filial' => [8, 3]],
            ['sku' => 'PEC-0123', 'nome' => 'Engate rápido hidráulico 1/2" macho (ISO A)', 'local' => 'Galpão A · Corredor 3 · Prateleira E3', 'lote' => 'L2026-102', 'qtd' => 38, 'min' => 12, 'giro' => 'Médio giro', 'filial' => [14, 5]],
            ['sku' => 'PEC-0124', 'nome' => 'Engate rápido hidráulico 1/2" fêmea (ISO A)', 'local' => 'Galpão A · Corredor 3 · Prateleira E4', 'lote' => 'L2026-103', 'qtd' => 36, 'min' => 12, 'giro' => 'Médio giro', 'filial' => [14, 5]],
            ['sku' => 'PEC-0125', 'nome' => 'Cilindro hidráulico dupla ação 80 x 300 mm', 'local' => 'Galpão A · Corredor 3 · Prateleira E5', 'lote' => 'L2025-188', 'serie' => ['CH80-25-', 1764], 'qtd' => 3, 'min' => 1, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0126', 'nome' => 'Bomba hidráulica de engrenagem 31 cm³', 'local' => 'Galpão A · Corredor 3 · Prateleira E6', 'lote' => 'L2025-190', 'serie' => ['BH31-', 87732], 'qtd' => 3, 'min' => 1, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0127', 'nome' => 'Válvula de comando hidráulico 3 vias', 'local' => 'Galpão A · Corredor 3 · Prateleira E7', 'lote' => 'L2025-176', 'serie' => ['VC3-', 552190], 'qtd' => 4, 'min' => 1, 'giro' => 'Baixo giro', 'filial' => [2, 0]],
            ['sku' => 'PEC-0128', 'nome' => 'Terminal de direção', 'local' => 'Galpão A · Corredor 3 · Prateleira E8', 'lote' => 'L2026-064', 'qtd' => 10, 'min' => 4, 'giro' => 'Médio giro', 'filial' => [4, 1]],

            // ----- Lubrificantes e fluidos -----
            ['sku' => 'PEC-0129', 'nome' => 'Óleo hidráulico ISO VG 68 (balde 20 L)', 'local' => 'Depósito de lubrificantes · Estante 1', 'lote' => 'L2026-150', 'qtd' => 6, 'min' => 12, 'giro' => 'Alto giro', 'filial' => [8, 5]],
            ['sku' => 'PEC-0130', 'nome' => 'Óleo de motor 15W-40 API CK-4 (balde 20 L)', 'local' => 'Depósito de lubrificantes · Estante 2', 'lote' => 'L2026-151', 'qtd' => 34, 'min' => 12, 'giro' => 'Alto giro', 'filial' => [14, 6]],
            ['sku' => 'PEC-0131', 'nome' => 'Óleo universal de transmissão e hidráulico UTTO (tambor 200 L)', 'local' => 'Depósito de lubrificantes · Piso 1', 'lote' => 'L2026-152', 'qtd' => 5, 'min' => 2, 'giro' => 'Médio giro', 'filial' => null],
            ['sku' => 'PEC-0132', 'nome' => 'Graxa de lítio EP-2 (balde 20 kg)', 'local' => 'Depósito de lubrificantes · Estante 3', 'lote' => 'L2026-153', 'qtd' => 6, 'min' => 5, 'giro' => 'Alto giro', 'filial' => [4, 2]],
            ['sku' => 'PEC-0133', 'nome' => 'Fluido de arrefecimento concentrado (galão 5 L)', 'local' => 'Depósito de lubrificantes · Estante 4', 'lote' => 'L2026-154', 'qtd' => 30, 'min' => 10, 'giro' => 'Médio giro', 'filial' => [12, 4]],
            ['sku' => 'PEC-0134', 'nome' => 'ARLA 32 (galão 20 L)', 'local' => 'Depósito de lubrificantes · Piso 2', 'lote' => 'L2026-155', 'qtd' => 40, 'min' => 15, 'giro' => 'Alto giro', 'filial' => [18, 6]],
            ['sku' => 'PEC-0135', 'nome' => 'Óleo 2 tempos para motosserra e roçadeira (frasco 500 ml)', 'local' => 'Depósito de lubrificantes · Estante 5', 'lote' => 'L2026-156', 'qtd' => 48, 'min' => 12, 'giro' => 'Médio giro', 'filial' => null],

            // ----- Elétrica -----
            ['sku' => 'PEC-0136', 'nome' => 'Bateria 12 V 150 Ah', 'local' => 'Sala elétrica · Estante 1', 'lote' => 'L2026-079', 'serie' => ['BAT150-', 2609114], 'qtd' => 8, 'min' => 3, 'giro' => 'Médio giro', 'filial' => [4, 1]],
            ['sku' => 'PEC-0137', 'nome' => 'Alternador 12 V 120 A', 'local' => 'Sala elétrica · Estante 2', 'lote' => 'L2025-201', 'serie' => ['ALT120-', 44871], 'qtd' => 3, 'min' => 1, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0138', 'nome' => 'Motor de partida 12 V 3,0 kW', 'local' => 'Sala elétrica · Estante 2', 'lote' => 'L2025-202', 'serie' => ['MP30-', 31902], 'qtd' => 3, 'min' => 1, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0139', 'nome' => 'Farol de trabalho LED 48 W', 'local' => 'Sala elétrica · Estante 3', 'lote' => 'L2026-080', 'qtd' => 24, 'min' => 8, 'giro' => 'Médio giro', 'filial' => [8, 3]],
            ['sku' => 'PEC-0140', 'nome' => 'Sensor de rotação (pickup magnético)', 'local' => 'Sala elétrica · Estante 4', 'lote' => 'L2026-081', 'qtd' => 12, 'min' => 4, 'giro' => 'Médio giro', 'filial' => [5, 2]],
            ['sku' => 'PEC-0141', 'nome' => 'Sensor de temperatura do líquido de arrefecimento', 'local' => 'Sala elétrica · Estante 4', 'lote' => 'L2026-082', 'qtd' => 10, 'min' => 4, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0142', 'nome' => 'Relé auxiliar 12 V 40 A', 'local' => 'Sala elétrica · Estante 5', 'lote' => 'L2026-083', 'qtd' => 50, 'min' => 15, 'giro' => 'Médio giro', 'filial' => [20, 6]],
            ['sku' => 'PEC-0143', 'nome' => 'Kit de fusíveis lâmina sortidos (120 peças)', 'local' => 'Sala elétrica · Estante 5', 'lote' => 'L2026-084', 'qtd' => 15, 'min' => 5, 'giro' => 'Médio giro', 'filial' => [6, 2]],
            ['sku' => 'PEC-0144', 'nome' => 'Chicote elétrico do monitor de plantio', 'local' => 'Sala elétrica · Estante 6', 'lote' => 'L2025-199', 'serie' => ['CHM-', 70315], 'qtd' => 4, 'min' => 2, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0145', 'nome' => 'Receptor GNSS RTK do piloto automático', 'local' => 'Sala elétrica · Armário trancado', 'lote' => 'L2025-160', 'serie' => ['RTK-77A', 41298], 'qtd' => 2, 'min' => 0, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0146', 'nome' => 'Monitor de plantio com tela de 7"', 'local' => 'Sala elétrica · Armário trancado', 'lote' => 'L2025-161', 'serie' => ['MON7-', 10822], 'qtd' => 3, 'min' => 1, 'giro' => 'Baixo giro', 'filial' => null],

            // ----- Plantio -----
            ['sku' => 'PEC-0147', 'nome' => 'Disco de corte liso 18" da plantadeira', 'local' => 'Galpão B · Corredor 5 · Prateleira F1', 'lote' => 'L2026-121', 'qtd' => 14, 'min' => 12, 'giro' => 'Médio giro', 'filial' => [10, 4]],
            ['sku' => 'PEC-0148', 'nome' => 'Disco duplo desencontrado 15" (sulcador de semente)', 'local' => 'Galpão B · Corredor 5 · Prateleira F2', 'lote' => 'L2026-122', 'qtd' => 40, 'min' => 16, 'giro' => 'Médio giro', 'filial' => [16, 6]],
            ['sku' => 'PEC-0149', 'nome' => 'Disco dosador de sementes de milho (28 furos)', 'local' => 'Galpão B · Corredor 5 · Prateleira F3', 'lote' => 'L2026-123', 'qtd' => 60, 'min' => 20, 'giro' => 'Alto giro', 'filial' => [24, 8]],
            ['sku' => 'PEC-0150', 'nome' => 'Disco dosador de sementes de soja (90 furos)', 'local' => 'Galpão B · Corredor 5 · Prateleira F4', 'lote' => 'L2026-124', 'qtd' => 72, 'min' => 24, 'giro' => 'Alto giro', 'filial' => [30, 10]],
            ['sku' => 'PEC-0151', 'nome' => 'Facão sulcador de adubo com ponteira de vídea', 'local' => 'Galpão B · Corredor 5 · Prateleira F5', 'lote' => 'L2026-125', 'qtd' => 30, 'min' => 10, 'giro' => 'Médio giro', 'filial' => [3, 6]],
            ['sku' => 'PEC-0152', 'nome' => 'Roda compactadora em V (par)', 'local' => 'Galpão B · Corredor 5 · Prateleira F6', 'lote' => 'L2026-126', 'qtd' => 18, 'min' => 6, 'giro' => 'Médio giro', 'filial' => [7, 3]],
            ['sku' => 'PEC-0153', 'nome' => 'Mola de pressão da linha de plantio', 'local' => 'Galpão B · Corredor 5 · Prateleira F7', 'lote' => 'L2026-127', 'qtd' => 44, 'min' => 16, 'giro' => 'Médio giro', 'filial' => [18, 6]],
            ['sku' => 'PEC-0154', 'nome' => 'Mangote condutor de sementes', 'local' => 'Galpão B · Corredor 5 · Prateleira F8', 'lote' => 'L2026-128', 'qtd' => 36, 'min' => 12, 'giro' => 'Médio giro', 'filial' => [14, 5]],
            ['sku' => 'PEC-0155', 'nome' => 'Sensor ótico de sementes', 'local' => 'Galpão B · Corredor 5 · Prateleira F9', 'lote' => 'L2026-129', 'serie' => ['SOS-', 260451], 'qtd' => 20, 'min' => 8, 'giro' => 'Médio giro', 'filial' => [5, 4]],

            // ----- Pulverização -----
            ['sku' => 'PEC-0156', 'nome' => 'Ponta de pulverização leque 110-02 (cerâmica)', 'local' => 'Galpão B · Corredor 6 · Prateleira G1', 'lote' => 'L2026-132', 'qtd' => 80, 'min' => 120, 'giro' => 'Alto giro', 'filial' => [160, 60]],
            ['sku' => 'PEC-0157', 'nome' => 'Ponta antideriva 110-03 com indução de ar', 'local' => 'Galpão B · Corredor 6 · Prateleira G2', 'lote' => 'L2026-133', 'qtd' => 260, 'min' => 100, 'giro' => 'Alto giro', 'filial' => [110, 40]],
            ['sku' => 'PEC-0158', 'nome' => 'Ponta de jato cone vazio 80° (fungicida)', 'local' => 'Galpão B · Corredor 6 · Prateleira G3', 'lote' => 'L2026-134', 'qtd' => 180, 'min' => 60, 'giro' => 'Alto giro', 'filial' => [70, 25]],
            ['sku' => 'PEC-0159', 'nome' => 'Corpo de bico com antigotejo (diafragma)', 'local' => 'Galpão B · Corredor 6 · Prateleira G4', 'lote' => 'L2026-135', 'qtd' => 140, 'min' => 50, 'giro' => 'Médio giro', 'filial' => [60, 20]],
            ['sku' => 'PEC-0160', 'nome' => 'Válvula reguladora de pressão do pulverizador', 'local' => 'Galpão B · Corredor 6 · Prateleira G5', 'lote' => 'L2025-180', 'serie' => ['VRP-', 18843], 'qtd' => 6, 'min' => 2, 'giro' => 'Baixo giro', 'filial' => [2, 1]],
            ['sku' => 'PEC-0161', 'nome' => 'Manômetro com glicerina 0–20 bar', 'local' => 'Galpão B · Corredor 6 · Prateleira G6', 'lote' => 'L2026-136', 'qtd' => 15, 'min' => 5, 'giro' => 'Médio giro', 'filial' => [6, 2]],
            ['sku' => 'PEC-0162', 'nome' => 'Membrana da bomba de pistão-diafragma', 'local' => 'Galpão B · Corredor 6 · Prateleira G7', 'lote' => 'L2026-137', 'qtd' => 24, 'min' => 8, 'giro' => 'Médio giro', 'filial' => [10, 4]],
            ['sku' => 'PEC-0163', 'nome' => 'Peneira do tanque de calda (malha 30)', 'local' => 'Galpão B · Corredor 6 · Prateleira G8', 'lote' => 'L2026-138', 'qtd' => 16, 'min' => 6, 'giro' => 'Baixo giro', 'filial' => null],

            // ----- Colheita -----
            ['sku' => 'PEC-0164', 'nome' => 'Navalha serrilhada da barra de corte', 'local' => 'Galpão B · Corredor 7 · Prateleira H1', 'lote' => 'L2026-142', 'qtd' => 400, 'min' => 150, 'giro' => 'Alto giro', 'filial' => [90, 60]],
            ['sku' => 'PEC-0165', 'nome' => 'Dedo duplo da barra de corte', 'local' => 'Galpão B · Corredor 7 · Prateleira H2', 'lote' => 'L2026-143', 'qtd' => 120, 'min' => 40, 'giro' => 'Alto giro', 'filial' => [50, 16]],
            ['sku' => 'PEC-0166', 'nome' => 'Faca do picador de palha', 'local' => 'Galpão B · Corredor 7 · Prateleira H3', 'lote' => 'L2026-144', 'qtd' => 18, 'min' => 24, 'giro' => 'Alto giro', 'filial' => [12, 8]],
            ['sku' => 'PEC-0167', 'nome' => 'Corrente ASA 60 do elevador de grãos (caixa 3 m)', 'local' => 'Galpão B · Corredor 7 · Prateleira H4', 'lote' => 'L2026-145', 'qtd' => 5, 'min' => 4, 'giro' => 'Médio giro', 'filial' => [3, 1]],
            ['sku' => 'PEC-0168', 'nome' => 'Talisca de borracha do elevador de grãos', 'local' => 'Galpão B · Corredor 7 · Prateleira H5', 'lote' => 'L2026-146', 'qtd' => 60, 'min' => 20, 'giro' => 'Médio giro', 'filial' => [24, 8]],
            ['sku' => 'PEC-0169', 'nome' => 'Peneira superior regulável da colheitadeira', 'local' => 'Galpão B · Corredor 7 · Prateleira H6', 'lote' => 'L2025-170', 'serie' => ['PSR-', 5521], 'qtd' => 3, 'min' => 1, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0170', 'nome' => 'Helicoide do sem-fim de descarga', 'local' => 'Galpão B · Corredor 7 · Prateleira H7', 'lote' => 'L2025-171', 'qtd' => 2, 'min' => 0, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0171', 'nome' => 'Kit de pino e bucha do molinete', 'local' => 'Galpão B · Corredor 7 · Prateleira H8', 'lote' => 'L2026-147', 'qtd' => 30, 'min' => 10, 'giro' => 'Médio giro', 'filial' => null],

            // ----- Preparo de solo -----
            ['sku' => 'PEC-0172', 'nome' => 'Disco recortado 26" x 6 mm da grade aradora', 'local' => 'Galpão B · Corredor 8 · Prateleira I1', 'lote' => 'L2026-157', 'qtd' => 28, 'min' => 12, 'giro' => 'Médio giro', 'filial' => [12, 4]],
            ['sku' => 'PEC-0173', 'nome' => 'Disco liso 24" da grade niveladora', 'local' => 'Galpão B · Corredor 8 · Prateleira I2', 'lote' => 'L2026-158', 'qtd' => 36, 'min' => 14, 'giro' => 'Médio giro', 'filial' => null],
            ['sku' => 'PEC-0174', 'nome' => 'Ponteira de vídea do escarificador', 'local' => 'Galpão B · Corredor 8 · Prateleira I3', 'lote' => 'L2026-159', 'qtd' => 40, 'min' => 16, 'giro' => 'Alto giro', 'filial' => [4, 8]],
            ['sku' => 'PEC-0175', 'nome' => 'Parafuso de arado 5/8" com porca (caixa com 50)', 'local' => 'Galpão B · Corredor 8 · Prateleira I4', 'lote' => 'L2026-160', 'qtd' => 22, 'min' => 8, 'giro' => 'Médio giro', 'filial' => [8, 3]],

            // ----- Rodagem -----
            ['sku' => 'PEC-0176', 'nome' => 'Pneu agrícola 18.4-34 R1 (traseiro)', 'local' => 'Pátio coberto · Box 1', 'lote' => 'L2026-161', 'serie' => ['DOT-1826-', 7301], 'qtd' => 6, 'min' => 2, 'giro' => 'Baixo giro', 'filial' => [4, 2]],
            ['sku' => 'PEC-0177', 'nome' => 'Pneu agrícola 14.9-24 R1 (dianteiro)', 'local' => 'Pátio coberto · Box 2', 'lote' => 'L2026-162', 'serie' => ['DOT-1926-', 4412], 'qtd' => 6, 'min' => 2, 'giro' => 'Baixo giro', 'filial' => [4, 2]],
            ['sku' => 'PEC-0178', 'nome' => 'Câmara de ar 18.4-34', 'local' => 'Pátio coberto · Box 3', 'lote' => 'L2026-163', 'qtd' => 10, 'min' => 4, 'giro' => 'Baixo giro', 'filial' => null],

            // ----- Motor e transmissão -----
            ['sku' => 'PEC-0179', 'nome' => 'Kit de embreagem (platô + disco 13")', 'local' => 'Galpão A · Corredor 2 · Prateleira D7', 'lote' => 'L2025-205', 'serie' => ['EMB13-', 9087], 'qtd' => 3, 'min' => 1, 'giro' => 'Baixo giro', 'filial' => null],
            ['sku' => 'PEC-0180', 'nome' => 'Bico injetor do motor diesel 6 cilindros', 'local' => 'Galpão A · Corredor 1 · Prateleira C1', 'lote' => 'L2026-108', 'serie' => ['INJ-', 551204], 'qtd' => 7, 'min' => 6, 'giro' => 'Alto giro', 'filial' => [6, 2]],
        ];
    }

    /**
     * 20 máquinas da frota da matriz. "filial" = dados da unidade equivalente da Subempresa 01 (null = a filial não tem).
     *
     * @return list<array{sku: string, nome: string, tipo: string, modelo: string, ano: int, horas: float|null, serie: string, lote: string, status?: string, filial: array<string, mixed>|null}>
     */
    private function maquinas(): array
    {
        return [
            // ----- Tratores -----
            ['sku' => 'MAQ-0001', 'nome' => 'Trator John Deere 6125J', 'tipo' => 'Trator', 'modelo' => '6125J · 125 cv · preparo de solo e plantio', 'ano' => 2022, 'horas' => 2860.4, 'serie' => '1BM6125JCNM004512', 'lote' => 'L2022-014',
                'filial' => ['ano' => 2023, 'horas' => 1980.0, 'serie' => '1BM6125JPPM006871', 'lote' => 'L2023-009']],
            ['sku' => 'MAQ-0002', 'nome' => 'Trator John Deere 7230J', 'tipo' => 'Trator', 'modelo' => '7230J · 230 cv · grade pesada e plantadeira de 21 linhas', 'ano' => 2021, 'horas' => 4120.7, 'serie' => '1BM7230JTMD002219', 'lote' => 'L2021-022', 'filial' => null],
            ['sku' => 'MAQ-0003', 'nome' => 'Trator Massey Ferguson MF 4707', 'tipo' => 'Trator', 'modelo' => 'MF 4707 · 75 cv · pulverizador de arrasto e transporte', 'ano' => 2023, 'horas' => 1540.2, 'serie' => 'MF4707BR23A10457', 'lote' => 'L2023-011',
                'filial' => ['ano' => 2024, 'horas' => 820.5, 'serie' => 'MF4707BR24A13290', 'lote' => 'L2024-006']],
            ['sku' => 'MAQ-0004', 'nome' => 'Trator Massey Ferguson MF 292', 'tipo' => 'Trator', 'modelo' => 'MF 292 · 105 cv · reserva, aguardando venda', 'ano' => 2008, 'horas' => 11850.0, 'serie' => 'MF292BR08B03318', 'lote' => 'L2008-002', 'status' => 'Inativo', 'filial' => null],
            ['sku' => 'MAQ-0005', 'nome' => 'Trator New Holland T7.245', 'tipo' => 'Trator', 'modelo' => 'T7.245 · 245 cv · preparo de solo e plantio', 'ano' => 2020, 'horas' => 5210.9, 'serie' => 'NHT7245BR20Z01782', 'lote' => 'L2020-017', 'filial' => null],
            ['sku' => 'MAQ-0006', 'nome' => 'Trator Valtra BH194', 'tipo' => 'Trator', 'modelo' => 'BH194 · 190 cv · preparo de solo e transporte de grãos', 'ano' => 2019, 'horas' => 6730.3, 'serie' => 'VBH194BR19K00964', 'lote' => 'L2019-012',
                'filial' => ['ano' => 2021, 'horas' => 4410.6, 'serie' => 'VBH194BR21K02377', 'lote' => 'L2021-015', 'status' => 'Em manutenção']],
            ['sku' => 'MAQ-0007', 'nome' => 'Trator Case IH Magnum 340', 'tipo' => 'Trator', 'modelo' => 'Magnum 340 · 340 cv · plantio de alta capacidade', 'ano' => 2022, 'horas' => 2310.0, 'serie' => 'JJAMAG340BR22H0418', 'lote' => 'L2022-019', 'filial' => null],

            // ----- Colheitadeiras -----
            ['sku' => 'MAQ-0008', 'nome' => 'Colheitadeira John Deere S680', 'tipo' => 'Colheitadeira', 'modelo' => 'S680 · plataforma draper de 40 pés · soja e milho', 'ano' => 2020, 'horas' => 3480.5, 'serie' => '1H0S680SAL0785521', 'lote' => 'L2020-005', 'status' => 'Em manutenção', 'filial' => null],
            ['sku' => 'MAQ-0009', 'nome' => 'Colheitadeira Case IH Axial-Flow 8250', 'tipo' => 'Colheitadeira', 'modelo' => 'Axial-Flow 8250 · rotor axial · soja, milho e trigo', 'ano' => 2022, 'horas' => 1910.2, 'serie' => 'YJG8250BR22F0233', 'lote' => 'L2022-003',
                'filial' => ['ano' => 2021, 'horas' => 2640.8, 'serie' => 'YJG8250BR21F0167', 'lote' => 'L2021-004']],
            ['sku' => 'MAQ-0010', 'nome' => 'Colheitadeira New Holland CR 8.90', 'tipo' => 'Colheitadeira', 'modelo' => 'CR 8.90 · rotor duplo · milho safrinha', 'ano' => 2021, 'horas' => 2575.6, 'serie' => 'NHCR890BR21Y0391', 'lote' => 'L2021-007', 'filial' => null],

            // ----- Pulverizadores -----
            ['sku' => 'MAQ-0011', 'nome' => 'Pulverizador autopropelido Jacto Uniport 3030', 'tipo' => 'Pulverizador', 'modelo' => 'Uniport 3030 · barra de 32 m · tanque de 3.000 L', 'ano' => 2021, 'horas' => 3920.4, 'serie' => 'UNP3030-21-08814', 'lote' => 'L2021-010',
                'filial' => ['ano' => 2022, 'horas' => 2715.3, 'serie' => 'UNP3030-22-09977', 'lote' => 'L2022-008']],
            ['sku' => 'MAQ-0012', 'nome' => 'Pulverizador autopropelido John Deere M4040', 'tipo' => 'Pulverizador', 'modelo' => 'M4040 · barra de 36 m · tanque de 4.000 L', 'ano' => 2023, 'horas' => 1460.1, 'serie' => '1N0M4040PBR012264', 'lote' => 'L2023-004', 'status' => 'Em manutenção', 'filial' => null],
            ['sku' => 'MAQ-0013', 'nome' => 'Pulverizador autopropelido Stara Imperador 3.0', 'tipo' => 'Pulverizador', 'modelo' => 'Imperador 3.0 · barra de 30 m · defensivos e fertilizante líquido', 'ano' => 2022, 'horas' => 2290.0, 'serie' => 'STI30-22-04461', 'lote' => 'L2022-012',
                'filial' => ['ano' => 2023, 'horas' => 1385.7, 'serie' => 'STI30-23-05129', 'lote' => 'L2023-013']],

            // ----- Plantadeiras (implementos: sem horímetro) -----
            ['sku' => 'MAQ-0014', 'nome' => 'Plantadeira Jumil JM 3090 PD', 'tipo' => 'Plantadeira', 'modelo' => 'JM 3090 PD · 13 linhas · plantio direto de soja e milho', 'ano' => 2021, 'horas' => null, 'serie' => 'JM3090-21-1142', 'lote' => 'L2021-018',
                'filial' => ['ano' => 2022, 'horas' => null, 'serie' => 'JM3090-22-1386', 'lote' => 'L2022-016']],
            ['sku' => 'MAQ-0015', 'nome' => 'Plantadeira John Deere 2130', 'tipo' => 'Plantadeira', 'modelo' => '2130 · plantio direto de soja e milho', 'ano' => 2022, 'horas' => null, 'serie' => '1DD2130BR22P0571', 'lote' => 'L2022-021',
                'filial' => ['ano' => 2023, 'horas' => null, 'serie' => '1DD2130BR23P0802', 'lote' => 'L2023-017']],
            ['sku' => 'MAQ-0016', 'nome' => 'Plantadeira pneumática 21 linhas', 'tipo' => 'Plantadeira', 'modelo' => 'PNEU-21 · plantio de soja em grandes áreas', 'ano' => 2020, 'horas' => null, 'serie' => 'PP21-20-0318', 'lote' => 'L2020-021', 'filial' => null],

            // ----- Outros implementos -----
            ['sku' => 'MAQ-0017', 'nome' => 'Distribuidor de calcário e adubo Stara Hércules 10000', 'tipo' => 'Outro', 'modelo' => 'Hércules 10000 · distribuição a lanço de calcário e adubo', 'ano' => 2020, 'horas' => null, 'serie' => 'HRC10K-20-2290', 'lote' => 'L2020-014',
                'filial' => ['ano' => 2021, 'horas' => null, 'serie' => 'HRC10K-21-2614', 'lote' => 'L2021-020']],
            ['sku' => 'MAQ-0018', 'nome' => 'Grade aradora controle remoto 16 discos 28"', 'tipo' => 'Outro', 'modelo' => 'GACR 16 x 28 · preparo primário do solo', 'ano' => 2018, 'horas' => null, 'serie' => 'GACR1628-18-0745', 'lote' => 'L2018-006', 'filial' => null],
            ['sku' => 'MAQ-0019', 'nome' => 'Carreta graneleira 18 t', 'tipo' => 'Outro', 'modelo' => 'Graneleiro 18 t · transbordo de grãos na colheita', 'ano' => 2021, 'horas' => null, 'serie' => 'GR18-21-0933', 'lote' => 'L2021-024',
                'filial' => ['ano' => 2022, 'horas' => null, 'serie' => 'GR18-22-1107', 'lote' => 'L2022-023']],
            ['sku' => 'MAQ-0020', 'nome' => 'Plataforma de milho 12 linhas', 'tipo' => 'Outro', 'modelo' => 'Plataforma 12 linhas · espaçamento de 45 cm · acoplada à colheitadeira', 'ano' => 2022, 'horas' => null, 'serie' => 'PM12-22-0457', 'lote' => 'L2022-025', 'filial' => null],
        ];
    }
}
