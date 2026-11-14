<?php

use App\Models\RadarMind\Item;
use App\Models\RadarMind\StockMovement;
use App\Models\RadarMind\Unit;
use App\Models\User;
use App\Support\Relatorios\Relatorio;
use Database\Seeders\RadarMindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RadarMindSeeder::class);
    $this->unidadeMatriz = Unit::where('code', 'MAT')->first();
    $this->sub01 = Unit::where('code', 'SUB01')->first();
    $this->matriz = User::factory()->matriz()->create(['name' => 'Ana da Matriz', 'unit_id' => $this->unidadeMatriz->id]);
});

/**
 * @return list<string>
 */
function linhasDoCsv(string $conteudo): array
{
    return explode("\r\n", trim(ltrim($conteudo, "\xEF\xBB\xBF")));
}

it('lets the buttons choose the format, without the export format field', function () {
    $this->actingAs($this->matriz)->get(route('radarmind.reports.index'))
        ->assertOk()
        ->assertDontSee('Formato de exportação')
        ->assertSee('formaction="'.route('radarmind.reports.preview').'"', false)
        ->assertSee('formaction="'.route('radarmind.reports.export').'"', false)
        ->assertSee('Baixar CSV (Excel)')
        ->assertSee('data-somente-para="movimentacoes"', false);
});

it('lists the items that need replenishment and how much to restock', function () {
    $this->actingAs($this->matriz)->get(route('radarmind.reports.preview', ['type' => 'estoque']))
        ->assertOk()
        ->assertSeeInOrder(['Itens que precisam de reposição', 'PEC-0103', 'Peças avulsas', 'Maquinário'])
        ->assertViewHas('relatorio', function (Relatorio $relatorio) {
            $filtroDeOleo = collect($relatorio->secoes[0]->linhas)->firstWhere('Código', 'PEC-0103');

            // 2 em estoque, mínimo 10: precisa passar de 13 (10 × 1,25) para sair do alerta
            return $filtroDeOleo['Repor'] === 12 && $filtroDeOleo['Situação'] === 'Crítico' && $filtroDeOleo['Prioridade'] === 'Urgente';
        });
});

it('shows only the chosen unit and hides the unit column', function () {
    $this->actingAs($this->matriz)->get(route('radarmind.reports.preview', ['type' => 'estoque', 'unit_id' => $this->sub01->id]))
        ->assertOk()
        ->assertViewHas('relatorio', function (Relatorio $relatorio) {
            $pecas = $relatorio->secoes[1];

            return ! array_key_exists('Unidade', $pecas->colunas)
                && collect($pecas->linhas)->pluck('Código')->sort()->values()->all() === ['FER-0012', 'PEC-0103', 'PEC-0210']
                && $relatorio->parametros['Unidade'] === 'Subempresa 01';
        });
});

it('exports a csv organized in blocks that excel understands', function () {
    $resposta = $this->actingAs($this->matriz)->get(route('radarmind.reports.export', ['type' => 'estoque']));

    $resposta->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($resposta->headers->get('Content-Disposition'))->toContain('relatorio-estoque-consolidado-');

    $conteudo = $resposta->streamedContent();
    $linhas = linhasDoCsv($conteudo);

    expect($conteudo)->toStartWith("\xEF\xBB\xBF")
        ->and($linhas[1])->toBe('"RELATÓRIO DE ESTOQUE"')
        ->and($linhas)->toContain('"Gerado por";"Ana da Matriz"')
        ->and($linhas)->toContain('RESUMO')
        ->and($linhas)->toContain('Indicador;Valor;Detalhe')
        ->and($linhas)->toContain('Código;Item;Unidade;"Em estoque";Mínimo;Repor;Situação;Giro;Prioridade')
        ->and($linhas)->toContain('PEC-0103;"Filtro de óleo hidráulico";"Subempresa 01";2;10;12;Crítico;"Alto giro";Urgente')
        ->and(collect($linhas)->first(fn (string $linha) => str_starts_with($linha, '"PEÇAS AVULSAS (')))->toBe('"PEÇAS AVULSAS (7 registros)"');
});

it('summarizes the movements of the period by unit and item, with who registered them', function () {
    $correia = Item::where('sku', 'PEC-0118')->first();
    StockMovement::create([
        'item_id' => $correia->id, 'unit_id' => $this->unidadeMatriz->id, 'type' => 'Entrada', 'quantity' => 5,
        'movement_date' => '2026-11-10', 'notes' => '=SOMA(A1:A9)', 'user_id' => $this->matriz->id,
    ]);
    $periodo = ['type' => 'movimentacoes', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30'];

    $this->actingAs($this->matriz)->get(route('radarmind.reports.preview', $periodo))
        ->assertOk()
        ->assertSee('01/11/2026 a 30/11/2026')
        ->assertViewHas('relatorio', function (Relatorio $relatorio) {
            [$porUnidade, $porItem, $registros] = $relatorio->secoes;

            return count($registros->linhas) === 1
                && $registros->linhas[0]['Responsável'] === 'Ana da Matriz'
                && $porUnidade->linhas[0] === ['Unidade' => 'Matriz', 'Registros' => 1, 'Entradas (un.)' => 5, 'Saídas (un.)' => 0, 'Saldo (un.)' => 5]
                && $porItem->linhas[0]['Código'] === 'PEC-0118';
        });

    // No CSV a observação que parece fórmula vira texto
    $linhas = linhasDoCsv($this->get(route('radarmind.reports.export', $periodo))->streamedContent());

    expect($linhas)->toContain('10/11/2026;Matriz;PEC-0118;"Correia do alternador";Entrada;5;"Ana da Matriz";\'=SOMA(A1:A9)');
});

it('accepts only an end date but not an end date before the start date', function () {
    $this->actingAs($this->matriz)
        ->get(route('radarmind.reports.preview', ['type' => 'movimentacoes', 'end_date' => '2026-10-31']))
        ->assertOk()
        ->assertSee('Até 31/10/2026');

    $this->from(route('radarmind.reports.index'))
        ->get(route('radarmind.reports.preview', ['type' => 'movimentacoes', 'start_date' => '2026-11-10', 'end_date' => '2026-11-01']))
        ->assertRedirect(route('radarmind.reports.index'))
        ->assertSessionHasErrors(['end_date' => 'A data final deve ser igual ou posterior à data inicial.']);
});

it('adds up every column of the operational situation and keeps the ranking position of a filtered unit', function () {
    $this->actingAs($this->matriz)->get(route('radarmind.reports.preview', ['type' => 'desempenho']))
        ->assertOk()
        ->assertViewHas('relatorio', fn (Relatorio $relatorio) => $relatorio->secoes[1]->total['Peças em estoque (un.)'] === Item::where('category', 'peca')->sum('quantity'));

    $posicaoNoConsolidado = fn (Relatorio $relatorio) => collect($relatorio->secoes[0]->linhas)->firstWhere('Unidade', 'Subempresa 01')['Posição'];
    $consolidado = $this->get(route('radarmind.reports.preview', ['type' => 'desempenho']))->viewData('relatorio');

    $this->get(route('radarmind.reports.preview', ['type' => 'desempenho', 'unit_id' => $this->sub01->id]))
        ->assertViewHas('relatorio', fn (Relatorio $relatorio) => $relatorio->secoes[0]->linhas[0]['Posição'] === $posicaoNoConsolidado($consolidado));
});
