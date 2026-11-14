<?php

use App\Models\Os;
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
    $this->unidade = Unit::where('code', 'SUB01')->first();
    $this->unidadeMatriz = Unit::where('code', 'MAT')->first();
    $this->subempresa = User::factory()->create(['unit_id' => $this->unidade->id]);
    $this->matriz = User::factory()->matriz()->create(['unit_id' => $this->unidadeMatriz->id]);
});

function dadosDoItem(array $alteracoes = []): array
{
    return $alteracoes + [
        'category' => 'peca', 'sku' => 'PEC-0999', 'name' => 'Filtro de ar', 'quantity' => 5,
        'minimum_quantity' => 2, 'status' => 'Ativo', 'turnover' => 'Médio giro',
        'location' => 'Corredor 1 · Prateleira A2', 'lot' => 'L2026-099', 'serial_number' => 'SN0999-1001 a SN0999-1005',
    ];
}

// ===== Dashboard da subempresa =====

it('links the dashboard shortcuts to the system pages instead of private forms', function () {
    $this->actingAs($this->subempresa)
        ->get(route('subempresa'))
        ->assertOk()
        ->assertSee('Subempresa 01')
        ->assertSee(route('radarmind.inventory.index').'#form-item', false)
        ->assertSee(route('radarmind.movements.index').'#registrar', false)
        ->assertSee(route('os.create'), false)
        ->assertSee(route('pedidos-reposicao.index'), false)
        ->assertDontSee('<dialog', false);
});

it('returns only the data of the logged unit on the dashboard', function () {
    $this->actingAs($this->subempresa)
        ->getJson(route('subempresa.estoque'))
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonMissing(['codigo' => 'PEC-0118']);

    $this->getJson(route('subempresa.dashboard'))
        ->assertOk()
        ->assertJsonPath('subempresa.nome', 'Subempresa 01')
        ->assertJsonPath('kpis.totalItens', 4)
        ->assertJsonPath('kpis.itensEmAlerta', 3)
        ->assertJsonPath('kpis.reposicoesPendentes', 1);
});

// ===== Estoque =====

it('shows the subempresa only its own stock and the matriz the stock of every unit', function () {
    $this->actingAs($this->subempresa)->get(route('radarmind.inventory.index'))
        ->assertOk()->assertSee('Correia dentada')->assertDontSee('Correia do alternador');

    $this->actingAs($this->matriz)->get(route('radarmind.inventory.index'))
        ->assertOk()->assertSee('Correia dentada')->assertSee('Correia do alternador');
});

it('separates the matriz own stock from the stock of the subempresas', function () {
    $this->actingAs($this->matriz)->get(route('radarmind.inventory.index'))
        ->assertOk()
        ->assertSeeInOrder(['Estoque da Matriz', 'Correia do alternador', 'Estoque das Subempresas', 'Correia dentada']);

    $this->actingAs($this->subempresa)->get(route('radarmind.inventory.index'))
        ->assertOk()
        ->assertDontSee('Estoque da Matriz')
        ->assertDontSee('Estoque das Subempresas');
});

it('requires the mandatory stock fields and marks them on the form', function () {
    $this->actingAs($this->subempresa)->get(route('radarmind.inventory.index'))
        ->assertOk()
        ->assertSee('Campos obrigatórios')
        ->assertSee('name="location"', false);

    $this->post(route('radarmind.inventory.store'), ['category' => 'maq', 'sku' => 'MAQ-0999', 'name' => 'Trator novo'])
        ->assertSessionHasErrors([
            'location' => 'Preencha o campo Localização física.',
            'quantity' => 'Preencha o campo Quantidade disponível.',
            'minimum_quantity' => 'Preencha o campo Quantidade mínima.',
            'lot' => 'Preencha o campo Lote.',
            'serial_number' => 'Preencha o campo Número de série.',
            'status' => 'Preencha o campo Status.',
            'turnover' => 'Preencha o campo Classificação de giro.',
        ])
        ->assertSessionDoesntHaveErrors(['machine_type', 'model_application', 'year', 'hour_meter']);

    // Tipo, modelo, ano e horímetro continuam opcionais
    $this->post(route('radarmind.inventory.store'), dadosDoItem(['category' => 'maq', 'sku' => 'MAQ-0999', 'name' => 'Trator novo', 'quantity' => 1, 'minimum_quantity' => 0]))
        ->assertSessionHasNoErrors();
});

it('does not ask for an image when registering or editing an item', function () {
    $itemProprio = Item::where('sku', 'PEC-0210')->first();

    $this->actingAs($this->subempresa)->get(route('radarmind.inventory.index'))
        ->assertOk()->assertDontSee('type="file"', false)->assertDontSee('Imagem do item');
    $this->get(route('radarmind.inventory.edit', $itemProprio))
        ->assertOk()->assertDontSee('type="file"', false);
});

it('always creates the item in the unit of who registers it', function () {
    $this->actingAs($this->subempresa)
        ->post(route('radarmind.inventory.store'), dadosDoItem(['unit_id' => $this->unidadeMatriz->id]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->matriz)
        ->post(route('radarmind.inventory.store'), dadosDoItem(['sku' => 'PEC-0998', 'unit_id' => $this->unidade->id]))
        ->assertSessionHasNoErrors();

    expect(Item::where('sku', 'PEC-0999')->value('unit_id'))->toBe($this->unidade->id)
        ->and(Item::where('sku', 'PEC-0998')->value('unit_id'))->toBe($this->unidadeMatriz->id);
});

it('does not let the matriz change the stock of a subempresa (and vice-versa)', function () {
    $itemDaSubempresa = Item::where('sku', 'PEC-0210')->first();
    $itemDaMatriz = Item::where('sku', 'PEC-0118')->first();

    $this->actingAs($this->matriz)->get(route('radarmind.inventory.edit', $itemDaSubempresa))->assertForbidden();
    $this->actingAs($this->matriz)->put(route('radarmind.inventory.update', $itemDaSubempresa), dadosDoItem(['sku' => 'PEC-0210']))->assertForbidden();
    $this->actingAs($this->matriz)->delete(route('radarmind.inventory.destroy', $itemDaSubempresa))->assertForbidden();
    $this->actingAs($this->subempresa)->get(route('radarmind.inventory.edit', $itemDaMatriz))->assertForbidden();

    $this->actingAs($this->subempresa)->get(route('radarmind.inventory.edit', $itemDaSubempresa))->assertOk();
});

// ===== Movimentações =====

it('records movements only for items of the own unit', function () {
    $itemProprio = Item::where('sku', 'FER-0012')->first();
    $itemDaSubempresa = Item::where('sku', 'PEC-0210')->first();

    $this->actingAs($this->subempresa)
        ->post(route('radarmind.movements.store'), ['type' => 'Saída', 'item_id' => $itemProprio->id, 'quantity' => 2, 'movement_date' => now()->toDateString()])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->matriz)
        ->post(route('radarmind.movements.store'), ['type' => 'Entrada', 'item_id' => $itemDaSubempresa->id, 'quantity' => 2, 'movement_date' => now()->toDateString()])
        ->assertSessionHasErrors(['item_id' => 'Escolha um item do estoque da sua unidade.']);

    expect($itemProprio->fresh()->quantity)->toBe(4)
        ->and($itemDaSubempresa->fresh()->quantity)->toBe(3)
        ->and(StockMovement::latest('id')->first()->unit_id)->toBe($this->unidade->id);
});

it('shows the movements of every unit to the matriz and only the own ones to the subempresa', function () {
    $this->actingAs($this->subempresa)->get(route('radarmind.movements.index'))
        ->assertOk()->assertSee('Filtro de óleo hidráulico')->assertDontSee('Correia do alternador');

    $this->actingAs($this->matriz)->get(route('radarmind.movements.index'))
        ->assertOk()->assertSee('Filtro de óleo hidráulico')->assertSee('Correia do alternador');
});

it('separates the matriz own movements from the movements of the subempresas', function () {
    $this->actingAs($this->matriz)->get(route('radarmind.movements.index'))
        ->assertOk()
        ->assertSeeInOrder(['Movimentações da Matriz', 'PEC-0118 · Correia do alternador', 'Movimentações das Subempresas', 'PEC-0103 · Filtro de óleo hidráulico']);

    $this->actingAs($this->subempresa)->get(route('radarmind.movements.index'))
        ->assertOk()
        ->assertSee('Histórico de movimentações')
        ->assertDontSee('Movimentações das Subempresas');
});

// ===== O.S. =====

it('lets both register an O.S. but only the matriz sees all of them and changes the status', function () {
    $dados = [
        'solicitante' => 'Ana', 'maquina' => 'MAQ-0011 · Plantadeira pneumática', 'categoria' => 'maquinario',
        'tipo_problema' => 'Hidráulico', 'descricao' => 'Vazamento na mangueira do sistema hidráulico.', 'prioridade' => 'Alta',
    ];

    $this->actingAs($this->subempresa)->post(route('os.store'), $dados)->assertRedirect(route('os.index'));
    $this->actingAs($this->matriz)->post(route('os.store'), ['maquina' => 'Trator da matriz'] + $dados)->assertRedirect(route('os.index'));

    $osDaSubempresa = Os::where('unit_id', $this->unidade->id)->first();

    $this->actingAs($this->subempresa)->get(route('os.index'))
        ->assertOk()->assertSee('Plantadeira pneumática')->assertDontSee('Trator da matriz')
        ->assertDontSee(route('os.edit', $osDaSubempresa), false);
    $this->actingAs($this->subempresa)->get(route('os.edit', $osDaSubempresa))->assertForbidden();
    $this->actingAs($this->subempresa)->put(route('os.status', $osDaSubempresa), ['status' => 'Autorizada'])->assertForbidden();

    $this->actingAs($this->matriz)->get(route('os.index'))
        ->assertOk()->assertSee('Plantadeira pneumática')->assertSee('Trator da matriz');
    $this->actingAs($this->matriz)->put(route('os.status', $osDaSubempresa), ['status' => 'Autorizada'])->assertRedirect(route('os.index'));

    $this->actingAs($this->subempresa)
        ->getJson(route('subempresa.os'))
        ->assertJsonPath('data.0.status', 'Autorizada');
});

// ===== Pedidos de reposição =====

it('forces the subempresa to request replenishment only for itself', function () {
    $itemDaMatriz = Item::where('sku', 'PEC-0118')->first();
    $itemProprio = Item::where('sku', 'PEC-0210')->first();
    $dados = ['solicitante' => 'Ana', 'data_pedido' => '2026-11-07', 'quantidade' => 10, 'responsavel_nome' => 'Ana'];

    $this->actingAs($this->subempresa)
        ->postJson(route('pedidos-reposicao.store'), $dados + ['unit_id' => $this->unidadeMatriz->id, 'item_id' => $itemDaMatriz->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('item_id');

    $this->postJson(route('pedidos-reposicao.store'), $dados + ['unit_id' => $this->unidadeMatriz->id, 'item_id' => $itemProprio->id])
        ->assertCreated();

    expect(PedidoReposicao::latest('id')->first())
        ->unit_id->toBe($this->unidade->id)
        ->status->toBe('Em aberto');
});

it('lets the subempresa only confirm the arrival; cancelling or reopening stays with the matriz', function () {
    $pedido = PedidoReposicao::where('unit_id', $this->unidade->id)->first();

    foreach (['Cancelado', 'Em aberto'] as $status) {
        $this->actingAs($this->subempresa)
            ->patchJson(route('pedidos-reposicao.status', $pedido), ['status' => $status])
            ->assertForbidden();
    }

    $this->actingAs($this->subempresa)
        ->patchJson(route('pedidos-reposicao.status', $pedido), ['status' => 'Atendido', 'assinante' => 'Carlos Souza'])
        ->assertOk();

    expect($pedido->fresh()->status)->toBe('Atendido');

    // Depois de atendido a subempresa não edita nem exclui mais
    $this->actingAs($this->subempresa)->deleteJson(route('pedidos-reposicao.destroy', $pedido))->assertForbidden();
});

it('shows each profile the replenishment requests it is allowed to see', function () {
    $this->actingAs($this->subempresa)
        ->getJson(route('pedidos-reposicao.listar'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.responsavel_unidade', 'Subempresa 01')
        ->assertJsonPath('data.0.pode_mudar_status', false);

    $this->actingAs($this->matriz)
        ->getJson(route('pedidos-reposicao.listar'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.pode_mudar_status', true);
});

it('opens the replenishment page with the item already chosen (button of the stock alert)', function () {
    $item = Item::where('sku', 'PEC-0210')->first();

    $this->actingAs($this->subempresa)
        ->get(route('pedidos-reposicao.index', ['item' => $item->id]))
        ->assertOk()
        ->assertSee('data-item-sugerido="'.$item->id.'"', false);
});

// ===== Áreas exclusivas =====

it('keeps the dashboards and reports in their own profile area', function () {
    $this->actingAs($this->subempresa)->get(route('matriz'))->assertRedirect(route('home'));
    $this->actingAs($this->subempresa)->get(route('radarmind.reports.index'))->assertRedirect(route('home'));
    $this->actingAs($this->subempresa)->getJson(route('radarmind.reports.index'))->assertForbidden();

    $this->actingAs($this->matriz)->get(route('subempresa'))->assertRedirect(route('home'));
    $this->actingAs($this->matriz)->getJson(route('subempresa.dashboard'))->assertForbidden();
});

it('blocks a subempresa user without a linked unit', function () {
    $semUnidade = User::factory()->create(['unit_id' => null]);

    $this->actingAs($semUnidade)->get(route('subempresa'))->assertForbidden();
});
