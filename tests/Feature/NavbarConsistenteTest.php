<?php

use App\Models\Os;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\Unit;
use App\Models\User;
use Database\Seeders\RadarMindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * Links (texto + endereço) e subtítulo da navbar, sem o destaque do link ativo.
 *
 * @return array{subtitulo: string, links: array<int, string>}
 */
function menuDaPagina(string $html): array
{
    preg_match('/<span class="rm-subtitle[^"]*">(.*?)<\/span>/s', $html, $subtitulo);
    preg_match('/<ul class="rm-nav nav">(.*?)<\/ul>/s', $html, $lista);
    preg_match_all('/<a href="([^"]+)"[^>]*>.*?<span>(.*?)<\/span>/s', $lista[1] ?? '', $links, PREG_SET_ORDER);

    return [
        'subtitulo' => trim($subtitulo[1] ?? ''),
        'links' => array_map(fn (array $link) => trim($link[2]).' -> '.$link[1], $links),
    ];
}

beforeEach(function () {
    $this->seed(RadarMindSeeder::class);
});

/**
 * Menu de cada página aberta pelo usuário.
 *
 * @param  array<int, string>  $paginas
 * @return Collection<int, array{subtitulo: string, links: array<int, string>}>
 */
function menusDasPaginas(User $usuario, array $paginas)
{
    return collect($paginas)->map(fn (string $url) => menuDaPagina(test()->actingAs($usuario)->get($url)->assertOk()->getContent()));
}

it('keeps the same menu on every matriz page', function () {
    $matriz = User::factory()->matriz()->create(['unit_id' => Unit::where('code', 'MAT')->value('id')]);
    $os = Os::create([
        'solicitante' => 'Ana', 'maquina' => 'Trator', 'categoria' => 'maquinario', 'tipo_problema' => 'Mecânico',
        'descricao' => 'Barulho estranho no motor.', 'prioridade' => 'Alta', 'status' => 'Aguardando autorização',
    ]);

    $menus = menusDasPaginas($matriz, [
        route('matriz'), route('os.index'), route('os.create'), route('os.edit', $os),
        route('pedidos-reposicao.index'), route('radarmind.inventory.index'),
        route('radarmind.inventory.edit', Item::where('sku', 'PEC-0118')->first()), route('radarmind.movements.index'),
        route('alertas.index'), route('radarmind.service-orders.index'), route('radarmind.reports.index'),
    ]);

    expect($menus->first()['links'])->toHaveCount(6)
        ->and($menus->first()['subtitulo'])->toBe('GERÊNCIA DO AGRONEGÓCIO')
        ->and($menus->unique()->count())->toBe(1);
});

it('keeps the same menu on every subempresa page', function () {
    $subempresa = User::factory()->create(['unit_id' => Unit::where('code', 'SUB01')->value('id')]);

    $menus = menusDasPaginas($subempresa, [
        route('subempresa'), route('os.index'), route('os.create'), route('pedidos-reposicao.index'),
        route('radarmind.inventory.index'), route('radarmind.inventory.edit', Item::where('sku', 'PEC-0210')->first()),
        route('radarmind.movements.index'), route('alertas.index'),
    ]);

    expect($menus->first()['links'])->toHaveCount(6)
        ->and($menus->first()['subtitulo'])->toBe('GERÊNCIA DO AGRONEGÓCIO')
        ->and($menus->unique()->count())->toBe(1);
});

it('shows the same menu to the matriz and to the subempresa', function () {
    $matriz = User::factory()->matriz()->create(['unit_id' => Unit::where('code', 'MAT')->value('id')]);
    $subempresa = User::factory()->create(['unit_id' => Unit::where('code', 'SUB01')->value('id')]);

    expect(menusDasPaginas($matriz, [route('os.index')])->first())
        ->toBe(menusDasPaginas($subempresa, [route('os.index')])->first());
});
