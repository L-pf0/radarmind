<?php

use App\Models\Os;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\Unit;
use App\Models\User;
use Database\Seeders\RadarMindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RadarMindSeeder::class);
});

/**
 * Quantas vezes o card "Logado em Dashboard ..." aparece em cada página.
 *
 * @param  list<string>  $paginas
 * @return array<string, int>
 */
function cardsDeSessao(User $usuario, array $paginas, string $texto): array
{
    return collect($paginas)
        ->mapWithKeys(fn (string $url) => [$url => substr_count(test()->actingAs($usuario)->get($url)->assertOk()->getContent(), $texto)])
        ->all();
}

it('shows the matriz card once on every page the matriz can open', function () {
    $matriz = User::factory()->matriz()->create(['unit_id' => Unit::where('code', 'MAT')->value('id')]);
    $os = Os::create([
        'solicitante' => 'Ana', 'maquina' => 'Trator', 'categoria' => 'maquinario', 'tipo_problema' => 'Mecânico',
        'descricao' => 'Barulho estranho no motor.', 'prioridade' => 'Alta', 'status' => 'Aguardando autorização',
    ]);

    $paginas = [
        route('matriz'), route('os.index'), route('os.create'), route('os.edit', $os),
        route('pedidos-reposicao.index'), route('radarmind.inventory.index'),
        route('radarmind.inventory.edit', Item::where('sku', 'PEC-0118')->first()), route('radarmind.movements.index'),
        route('alertas.index'), route('radarmind.service-orders.index'), route('radarmind.reports.index'),
        route('radarmind.reports.preview', ['type' => 'estoque']),
    ];

    expect(array_unique(cardsDeSessao($matriz, $paginas, 'Logado em Dashboard Matriz')))->toBe([$paginas[0] => 1]);
});

it('shows the card of the own unit on every page of each subempresa', function (string $codigo, string $nome) {
    $subempresa = User::factory()->create(['unit_id' => Unit::where('code', $codigo)->value('id')]);
    $itemDaUnidade = Item::where('unit_id', $subempresa->unit_id)->first();

    $paginas = [
        route('subempresa'), route('os.index'), route('os.create'), route('pedidos-reposicao.index'),
        route('radarmind.inventory.index'), route('radarmind.inventory.edit', $itemDaUnidade),
        route('radarmind.movements.index'), route('alertas.index'),
    ];

    expect(array_unique(cardsDeSessao($subempresa, $paginas, "Logado em Dashboard {$nome}")))->toBe([$paginas[0] => 1])
        ->and(test()->get(route('os.index'))->getContent())->not->toContain('Logado em Dashboard Matriz');
})->with([
    ['SUB01', 'Subempresa 01'],
    ['SUB02', 'Subempresa 02'],
    ['SUB03', 'Subempresa 03'],
]);
