<?php

use App\Models\RadarMind\Item;
use App\Models\RadarMind\StockMovement;
use App\Models\RadarMind\Unit;
use App\Models\User;
use App\Support\MedidorDeDesempenho;
use Database\Seeders\RadarMindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RadarMindSeeder::class);
    $this->unidadeMatriz = Unit::where('code', 'MAT')->first();
    $this->sub01 = Unit::where('code', 'SUB01')->first();
    $this->matriz = User::factory()->matriz()->create(['unit_id' => $this->unidadeMatriz->id]);
    $this->subempresa = User::factory()->create(['unit_id' => $this->sub01->id]);
});

/**
 * @return array<string, int>
 */
function pontuacoesPorUnidade(): array
{
    return app(MedidorDeDesempenho::class)->medir()
        ->mapWithKeys(fn (array $desempenho) => [$desempenho['unidade']->code => $desempenho['pontuacao']])
        ->all();
}

it('scores each unit by its movements and its high turnover items in stock', function () {
    // Seeder: Matriz tem 2 movimentações e 1 item de alto giro; Subempresa 01 tem 1 e 2;
    // Subempresa 02 tem 1 e 0; Subempresa 03 não tem nenhum dos dois.
    expect(pontuacoesPorUnidade())->toBe(['MAT' => 75, 'SUB01' => 75, 'SUB02' => 25, 'SUB03' => 0]);
});

it('raises the score of a unit when it registers more movements', function () {
    $item = Item::where('sku', 'PEC-0210')->first();

    foreach (range(1, 3) as $vez) {
        $this->actingAs($this->subempresa)->post(route('radarmind.movements.store'), [
            'type' => 'Entrada', 'item_id' => $item->id, 'quantity' => 1, 'movement_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
    }

    // Subempresa 01 passa a liderar as movimentações (4); a Matriz (2) fica com metade desses pontos
    expect(pontuacoesPorUnidade())->toMatchArray(['SUB01' => 100, 'MAT' => 50]);
});

it('ignores high turnover items that are out of stock or inactive', function () {
    Item::where('sku', 'PEC-0103')->update(['quantity' => 0]);
    Item::where('sku', 'PEC-0210')->update(['status' => 'Inativo']);

    expect(app(MedidorDeDesempenho::class)->medirUnidade($this->sub01))
        ->itens_alto_giro->toBe(0)
        ->pontos_alto_giro->toBe(0);
});

it('scores every unit as zero when there is no data', function () {
    StockMovement::query()->delete();
    Item::query()->update(['turnover' => 'Baixo giro']);

    expect(array_values(array_unique(pontuacoesPorUnidade())))->toBe([0]);
});

it('shows the new performance criteria on the matriz dashboard ranking', function () {
    $this->actingAs($this->matriz)->get(route('matriz'))
        ->assertOk()
        ->assertSee('Pontuação = movimentações registradas (50%) + itens de alto giro em estoque (50%).')
        ->assertSee('2 movimentações')
        ->assertSee('2 itens de alto giro');
});

it('sends the performance of the unit to the subempresa dashboard', function () {
    $this->actingAs($this->subempresa)->getJson(route('subempresa.dashboard'))
        ->assertOk()
        ->assertJsonPath('desempenho.pontuacao', 75)
        ->assertJsonPath('desempenho.movimentacoes', 1)
        ->assertJsonPath('desempenho.itensAltoGiro', 2)
        ->assertJsonPath('desempenho.pontosMovimentacoes', 25)
        ->assertJsonPath('desempenho.pontosAltoGiro', 50);
});

it('hides the reports button only on the report pages', function () {
    $this->actingAs($this->matriz);

    $this->get(route('radarmind.reports.index'))->assertOk()->assertDontSee('Emitir relatórios');
    $this->get(route('radarmind.reports.preview', ['type' => 'estoque']))->assertOk()->assertDontSee('Emitir relatórios');
    $this->get(route('radarmind.inventory.index'))->assertOk()->assertSee('Emitir relatórios');
});

it('lists the performance criteria in the performance report', function () {
    $this->actingAs($this->matriz)->get(route('radarmind.reports.preview', ['type' => 'desempenho']))
        ->assertOk()
        ->assertSeeInOrder(['Desempenho (%)', 'Movimentações registradas', 'Itens de alto giro em estoque']);
});
