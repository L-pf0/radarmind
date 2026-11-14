<?php

use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\StockMovement;
use App\Models\RadarMind\Unit;
use App\Models\User;
use Database\Seeders\RadarMindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RadarMindSeeder::class);
    // A matriz é da unidade "Matriz": é nela que cadastra o próprio estoque e movimentações
    $this->matriz = User::factory()->matriz()->create(['unit_id' => Unit::where('code', 'MAT')->value('id')]);
});

it('redirects guests to login on every integrated page', function (string $rota) {
    $this->get(route($rota))->assertRedirect(route('login'));
})->with([
    'radarmind.inventory.index',
    'radarmind.movements.index',
    'alertas.index',
    'radarmind.service-orders.index',
    'radarmind.reports.index',
    'pedidos-reposicao.index',
    'os.create',
]);

it('renders every integrated page with the shared navbar', function (string $rota) {
    $this->actingAs($this->matriz)
        ->get(route($rota))
        ->assertOk()
        ->assertSee('rm-topbar', false)
        ->assertSee(route('pedidos-reposicao.index'), false);
})->with([
    'radarmind.inventory.index',
    'radarmind.movements.index',
    'alertas.index',
    'radarmind.service-orders.index',
    'radarmind.reports.index',
    'pedidos-reposicao.index',
]);

it('shows the logout button in the navbar of logged pages', function (string $rota) {
    $this->actingAs($this->matriz)
        ->get(route($rota))
        ->assertOk()
        ->assertSee('rm-logout-btn', false)
        ->assertSee('Deseja mesmo sair dessa conta?')
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('bootstrap.bundle.min.js', false);
})->with(['matriz', 'radarmind.inventory.index', 'pedidos-reposicao.index', 'os.index', 'os.create']);

it('applies the slides theme only to the internal pages, never to the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('tema-slide.css', false)
        ->assertDontSee('rm-tema-slide', false);

    foreach (['matriz', 'radarmind.inventory.index', 'pedidos-reposicao.index', 'alertas.index', 'os.index', 'os.create'] as $rota) {
        $this->actingAs($this->matriz)
            ->get(route($rota))
            ->assertOk()
            ->assertSee('tema-slide.css', false)
            ->assertSee('<main class="rm-tema-slide', false);
    }
});

it('hides the logout button on the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('rm-logout-btn', false);
});

it('sends the user back to login after logging out and blocks protected pages', function () {
    $this->actingAs($this->matriz)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get(route('matriz'))->assertRedirect(route('login'));
});

it('shows operational data on the matriz dashboard', function () {
    $this->actingAs($this->matriz)
        ->get(route('matriz'))
        ->assertOk()
        ->assertSee('Logado em Dashboard Matriz')
        ->assertSee('Filtro de óleo hidráulico')
        ->assertSee('Subempresa 01')
        ->assertSee(route('radarmind.reports.index'), false);
});

it('records a stock movement for the logged user and updates the balance', function () {
    $item = Item::where('sku', 'PEC-0118')->first();

    $this->actingAs($this->matriz)
        ->post(route('radarmind.movements.store'), [
            'type' => 'Saída',
            'item_id' => $item->id,
            'quantity' => 4,
            'movement_date' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($item->fresh()->quantity)->toBe(5)
        ->and(StockMovement::latest('id')->first()->user_id)->toBe($this->matriz->id);
});

it('exports the stock report as csv', function () {
    $this->actingAs($this->matriz)
        ->get(route('radarmind.reports.export', ['type' => 'estoque']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('creates, lists, updates and deletes replenishment orders through json', function () {
    $subempresa01 = Unit::where('code', 'SUB01')->first();
    $item = Item::where('sku', 'PEC-0103')->first();
    PedidoReposicao::query()->delete();

    $dados = [
        'solicitante' => 'Maria',
        'data_pedido' => '2026-11-03',
        'unit_id' => $subempresa01->id,
        'item_id' => $item->id,
        'quantidade' => 25,
        'responsavel_nome' => 'João',
    ];

    // A matriz pode pedir reposição para uma subempresa
    $id = $this->actingAs($this->matriz)
        ->postJson(route('pedidos-reposicao.store'), $dados)
        ->assertCreated()
        ->json('id');

    $this->getJson(route('pedidos-reposicao.listar'))
        ->assertOk()
        ->assertJsonPath('data.0.item', 'PEC-0103 · Filtro de óleo hidráulico')
        ->assertJsonPath('data.0.responsavel_unidade', 'Subempresa 01')
        ->assertJsonPath('data.0.status', 'Em aberto')
        ->assertJsonPath('data.0.data_pedido', '2026-11-03');

    $this->putJson(route('pedidos-reposicao.update', $id), ['quantidade' => 30] + $dados)->assertOk();

    expect(PedidoReposicao::find($id))
        ->quantidade->toBe(30)
        ->unit_id->toBe($subempresa01->id)
        ->user_id->toBe($this->matriz->id);

    $this->deleteJson(route('pedidos-reposicao.destroy', $id))->assertOk();

    expect(PedidoReposicao::count())->toBe(0);
});

it('rejects an item that does not belong to the chosen unit', function () {
    $itemDaMatriz = Item::where('sku', 'PEC-0118')->first();

    $this->actingAs($this->matriz)
        ->postJson(route('pedidos-reposicao.store'), [
            'solicitante' => 'Maria', 'data_pedido' => '2026-11-03', 'quantidade' => 2, 'responsavel_nome' => 'João',
            'unit_id' => Unit::where('code', 'SUB01')->value('id'), 'item_id' => $itemDaMatriz->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.item_id.0', 'Escolha um item do estoque da unidade selecionada.');
});

it('rejects an invalid replenishment order with portuguese messages', function () {
    $this->actingAs($this->matriz)
        ->postJson(route('pedidos-reposicao.store'), ['quantidade' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['solicitante', 'data_pedido', 'unit_id', 'item_id', 'quantidade', 'responsavel_nome'])
        ->assertJsonPath('errors.quantidade.0', 'A quantidade deve ser um número inteiro maior que zero.');
});
