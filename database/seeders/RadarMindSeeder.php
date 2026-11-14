<?php

namespace Database\Seeders;

use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\ServiceOrder;
use App\Models\RadarMind\StockMovement;
use App\Models\RadarMind\Unit;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração do módulo operacional (estoque, movimentações,
 * pedidos de reposição e O.S. das unidades). Pode rodar mais de uma vez sem duplicar.
 */
class RadarMindSeeder extends Seeder
{
    public function run(): void
    {
        $units = collect([
            ['name' => 'Matriz', 'code' => 'MAT'],
            ['name' => 'Subempresa 01', 'code' => 'SUB01'],
            ['name' => 'Subempresa 02', 'code' => 'SUB02'],
            ['name' => 'Subempresa 03', 'code' => 'SUB03'],
        ])->mapWithKeys(fn (array $unit) => [$unit['name'] => Unit::updateOrCreate(['code' => $unit['code']], $unit)]);

        $items = [
            ['unit' => 'Subempresa 01', 'category' => 'peca', 'sku' => 'PEC-0103', 'name' => 'Filtro de óleo hidráulico', 'location' => 'Corredor 2 · Prateleira B3', 'lot' => 'L2026-041', 'quantity' => 2, 'minimum_quantity' => 10, 'status' => 'Ativo', 'turnover' => 'Alto giro'],
            ['unit' => 'Matriz', 'category' => 'peca', 'sku' => 'PEC-0118', 'name' => 'Correia do alternador', 'location' => 'Corredor 1 · Prateleira A1', 'lot' => 'L2026-033', 'quantity' => 9, 'minimum_quantity' => 8, 'status' => 'Ativo', 'turnover' => 'Médio giro'],
            ['unit' => 'Matriz', 'category' => 'peca', 'sku' => 'PEC-0125', 'name' => 'Bico de pulverizador', 'location' => 'Corredor 4 · Prateleira C2', 'lot' => 'L2026-050', 'quantity' => 22, 'minimum_quantity' => 20, 'status' => 'Ativo', 'turnover' => 'Alto giro'],
            ['unit' => 'Subempresa 02', 'category' => 'peca', 'sku' => 'PEC-0140', 'name' => "Bomba d'água", 'location' => 'Corredor 3 · Prateleira A4', 'lot' => 'L2025-210', 'serial_number' => 'BA-77821', 'quantity' => 3, 'minimum_quantity' => 2, 'status' => 'Ativo', 'turnover' => 'Baixo giro'],
            ['unit' => 'Subempresa 03', 'category' => 'peca', 'sku' => 'PEC-0152', 'name' => 'Rolamento do cubo', 'location' => 'Corredor 5 · Prateleira B1', 'lot' => 'L2026-054', 'quantity' => 1, 'minimum_quantity' => 4, 'status' => 'Ativo', 'turnover' => 'Baixo giro'],
            ['unit' => 'Matriz', 'category' => 'maq', 'sku' => 'MAQ-0001', 'name' => 'Trator agrícola 75 cv', 'machine_type' => 'Trator', 'model_application' => 'Modelo A-75 · preparo de solo e tração', 'year' => 2021, 'hour_meter' => 3420, 'serial_number' => 'TR75-004512', 'lot' => 'L2021-008', 'quantity' => 3, 'minimum_quantity' => 1, 'status' => 'Ativo', 'turnover' => 'Baixo giro'],
            ['unit' => 'Matriz', 'category' => 'maq', 'sku' => 'MAQ-0002', 'name' => 'Colheitadeira de grãos', 'machine_type' => 'Colheitadeira', 'model_application' => 'Modelo C-30 · colheita de soja e milho', 'year' => 2019, 'hour_meter' => 5180, 'serial_number' => 'CG30-001877', 'lot' => 'L2019-003', 'quantity' => 1, 'minimum_quantity' => 1, 'status' => 'Em manutenção', 'turnover' => 'Baixo giro'],
            // Itens da Subempresa 01 vindos do dashboard da subempresa
            ['unit' => 'Subempresa 01', 'category' => 'peca', 'sku' => 'PEC-0210', 'name' => 'Correia dentada', 'location' => 'Corredor 1 · Prateleira C1', 'quantity' => 3, 'minimum_quantity' => 8, 'status' => 'Ativo', 'turnover' => 'Alto giro'],
            ['unit' => 'Subempresa 01', 'category' => 'peca', 'sku' => 'FER-0012', 'name' => 'Chave de impacto', 'location' => 'Armário de ferramentas', 'quantity' => 6, 'minimum_quantity' => 4, 'status' => 'Ativo', 'turnover' => 'Baixo giro'],
            ['unit' => 'Subempresa 01', 'category' => 'maq', 'sku' => 'MAQ-0011', 'name' => 'Plantadeira pneumática', 'machine_type' => 'Plantadeira', 'model_application' => 'Plantio direto de soja e milho', 'year' => 2022, 'hour_meter' => 960, 'serial_number' => 'PL12-003318', 'quantity' => 1, 'minimum_quantity' => 1, 'status' => 'Ativo', 'turnover' => 'Baixo giro'],
            ['unit' => 'Subempresa 02', 'category' => 'maq', 'sku' => 'MAQ-0007', 'name' => 'Pulverizador autopropelido', 'machine_type' => 'Pulverizador', 'model_application' => 'Modelo P-20 · aplicação de defensivos', 'year' => 2023, 'hour_meter' => 1870, 'serial_number' => 'PU20-009230', 'lot' => 'L2023-015', 'quantity' => 1, 'minimum_quantity' => 1, 'status' => 'Ativo', 'turnover' => 'Médio giro'],
        ];

        $itemsBySku = [];
        foreach ($items as $row) {
            $unit = $units[$row['unit']];
            unset($row['unit']);
            $itemsBySku[$row['sku']] = Item::updateOrCreate(['sku' => $row['sku']], $row + ['unit_id' => $unit->id]);
        }

        $movements = [
            ['date' => '2026-10-14', 'unit' => 'Matriz', 'sku' => 'PEC-0118', 'type' => 'Entrada', 'quantity' => 12],
            ['date' => '2026-10-13', 'unit' => 'Subempresa 01', 'sku' => 'PEC-0103', 'type' => 'Saída', 'quantity' => 8],
            ['date' => '2026-10-12', 'unit' => 'Subempresa 02', 'sku' => 'PEC-0140', 'type' => 'Entrada', 'quantity' => 2],
            ['date' => '2026-10-09', 'unit' => 'Matriz', 'sku' => 'MAQ-0001', 'type' => 'Saída', 'quantity' => 1],
        ];

        foreach ($movements as $movement) {
            StockMovement::firstOrCreate(
                ['item_id' => $itemsBySku[$movement['sku']]->id, 'movement_date' => $movement['date'], 'type' => $movement['type'], 'quantity' => $movement['quantity']],
                ['unit_id' => $units[$movement['unit']]->id],
            );
        }

        // Pedidos de reposição (página "Pedidos de reposição")
        $pedidos = [
            ['unit' => 'Subempresa 01', 'sku' => 'PEC-0103', 'quantidade' => 20, 'data' => '2026-10-14', 'responsavel' => 'Subempresa'],
            ['unit' => 'Matriz', 'sku' => 'PEC-0125', 'quantidade' => 30, 'data' => '2026-10-13', 'responsavel' => 'Matriz'],
        ];

        foreach ($pedidos as $pedido) {
            $item = $itemsBySku[$pedido['sku']];

            PedidoReposicao::firstOrCreate(
                ['item_id' => $item->id, 'status' => 'Em aberto'],
                [
                    'solicitante' => $pedido['responsavel'],
                    'unit_id' => $units[$pedido['unit']]->id,
                    'data_pedido' => $pedido['data'],
                    'item' => "{$item->sku} · {$item->name}",
                    'quantidade' => $pedido['quantidade'],
                    'responsavel_nome' => $pedido['responsavel'],
                    'unidade' => $pedido['unit'],
                ],
            );
        }

        ServiceOrder::firstOrCreate(
            ['title' => 'Revisão da colheitadeira C-30'],
            ['item_id' => $itemsBySku['MAQ-0002']->id, 'unit_id' => $units['Matriz']->id, 'description' => 'Verificar sistema de corte e lubrificação.', 'priority' => 'Alta', 'status' => 'Em andamento', 'opened_at' => '2026-10-10'],
        );
    }
}
