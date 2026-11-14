<?php

use App\Models\Alerta;
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
    $this->sub01 = Unit::where('code', 'SUB01')->first();
    $this->matriz = User::factory()->matriz()->create(['unit_id' => Unit::where('code', 'MAT')->value('id')]);
    $this->subempresa = User::factory()->create(['unit_id' => $this->sub01->id]);

    $this->item = Item::where('sku', 'PEC-0210')->first(); // Subempresa 01, saldo 3 / mín. 8

    $this->pedido = PedidoReposicao::create([
        'solicitante' => 'Ana', 'unit_id' => $this->sub01->id, 'data_pedido' => '2026-11-09',
        'item' => 'PEC-0210 · Correia dentada', 'item_id' => $this->item->id, 'quantidade' => 12,
        'responsavel_nome' => 'Ana', 'unidade' => 'Subempresa 01', 'status' => 'Em aberto',
        'user_id' => $this->subempresa->id,
    ]);

    Alerta::query()->delete(); // ignora o alerta de "pedido criado"
});

it('adds the arrived quantity to the stock and records an entry movement when the order is attended', function () {
    $this->actingAs($this->matriz)
        ->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Atendido', 'assinante' => 'Carlos Souza'])
        ->assertOk()
        ->assertJsonPath('mensagem', 'Chegada confirmada por Carlos Souza: 12 unidade(s) entraram no estoque de PEC-0210 · Correia dentada.');

    expect($this->item->fresh()->quantity)->toBe(15)
        ->and($this->pedido->fresh())
        ->status->toBe('Atendido')
        ->estoque_lancado_em->not->toBeNull()
        ->assinante_chegada->toBe('Carlos Souza');

    expect(StockMovement::latest('id')->first())
        ->item_id->toBe($this->item->id)
        ->unit_id->toBe($this->sub01->id)
        ->type->toBe('Entrada')
        ->quantity->toBe(12)
        ->notes->toBe("Reposição do pedido #{$this->pedido->id} atendida. Recebida por Carlos Souza.")
        ->user_id->toBe($this->matriz->id);
});

it('notifies "Pedido de estoque atendido para" the unit that received the stock', function () {
    $this->actingAs($this->matriz)->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Atendido', 'assinante' => 'Carlos Souza']);

    expect(Alerta::where('tipo_evento', 'PEDIDO_APROVADO')->sole())
        ->unit_id->toBe($this->sub01->id)
        ->referencia_id->toBe($this->pedido->id)
        ->qtd_recebida->toBe(12)
        ->qtd_atual->toBe(15)
        ->assinado_por->toBe('Carlos Souza');

    // A subempresa vê o aviso na Central de Alertas e no dashboard dela; a matriz também vê
    $this->actingAs($this->subempresa)->getJson(route('alertas.listar'))
        ->assertJsonPath('data.0.titulo', 'Pedido de estoque atendido para Subempresa 01')
        ->assertJsonPath('data.0.detalhes.mensagem', "O pedido #{$this->pedido->id} chegou e foi recebido por Carlos Souza: 12 unidade(s) entraram no estoque do item:")
        ->assertJsonPath('data.0.detalhes.assinado_por', 'Carlos Souza')
        ->assertJsonPath('data.0.detalhes.qtd_atual', 15);
    $this->getJson(route('subempresa.dashboard'))
        ->assertJsonPath('alertas.0.titulo', 'Pedido de estoque atendido para Subempresa 01');
    $this->actingAs($this->matriz)->getJson(route('alertas.listar'))
        ->assertJsonPath('data.0.titulo', 'Pedido de estoque atendido para Subempresa 01');
});

it('names the Matriz in the alert when the order was for the Matriz', function () {
    $itemDaMatriz = Item::where('sku', 'PEC-0118')->first();
    $pedido = PedidoReposicao::create([
        'solicitante' => 'Matriz', 'unit_id' => $itemDaMatriz->unit_id, 'data_pedido' => '2026-11-09',
        'item' => 'PEC-0118 · Correia do alternador', 'item_id' => $itemDaMatriz->id, 'quantidade' => 4,
        'responsavel_nome' => 'Matriz', 'unidade' => 'Matriz', 'status' => 'Em aberto',
    ]);

    $this->actingAs($this->matriz)->patchJson(route('pedidos-reposicao.status', $pedido), ['status' => 'Atendido', 'assinante' => 'Carlos Souza'])->assertOk();

    $this->getJson(route('alertas.listar', ['gravidade' => 'CONCLUIDO']))
        ->assertJsonPath('data.0.titulo', 'Pedido de estoque atendido para Matriz');
    expect($itemDaMatriz->fresh()->quantity)->toBe(13);
});

it('shows a readable message for old "attended" alerts that have no received quantity', function () {
    Alerta::create([
        'tipo_evento' => 'PEDIDO_APROVADO', 'unit_id' => $this->sub01->id, 'referencia_id' => $this->pedido->id,
        'item_nome' => 'Correia dentada',
    ]);

    $this->actingAs($this->subempresa)->getJson(route('alertas.listar'))
        ->assertJsonPath('data.0.titulo', 'Pedido de estoque atendido para Subempresa 01')
        ->assertJsonPath('data.0.detalhes.mensagem', "O pedido #{$this->pedido->id} de reposição foi atendido para o item:");
});

it('requires the name of who signed the arrival to attend the order', function (?string $assinante) {
    $this->actingAs($this->matriz)
        ->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Atendido', 'assinante' => $assinante])
        ->assertUnprocessable()
        ->assertJsonPath('errors.assinante.0', 'Informe o nome de quem confirmou a chegada da reposição.');

    expect($this->item->fresh()->quantity)->toBe(3)
        ->and($this->pedido->fresh()->status)->toBe('Em aberto');
})->with([
    'sem assinante' => [null],
    'só espaços' => ['   '],
]);

it('shows "Atendido" and the signer on the registered orders list for the matriz and for the subempresa', function () {
    $this->actingAs($this->matriz)->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Atendido', 'assinante' => '  Carlos Souza  ']);

    foreach ([$this->matriz, $this->subempresa] as $usuario) {
        $this->actingAs($usuario)->getJson(route('pedidos-reposicao.listar'))
            ->assertJsonPath('data.0.id', $this->pedido->id)
            ->assertJsonPath('data.0.status', 'Atendido')
            ->assertJsonPath('data.0.assinante', 'Carlos Souza')
            ->assertJsonPath('data.0.chegada_em', $this->pedido->fresh()->estoque_lancado_em->format('d/m/Y H:i'));
    }
});

it('does not ask for a signer to cancel an order', function () {
    $this->actingAs($this->matriz)
        ->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Cancelado'])
        ->assertOk();

    expect($this->pedido->fresh()->assinante_chegada)->toBeNull();
});

it('opens the arrival confirmation from a link with ?pedido=ID (alerts and stock page)', function () {
    $this->actingAs($this->matriz)
        ->get(route('pedidos-reposicao.index', ['pedido' => $this->pedido->id]))
        ->assertOk()
        ->assertSee('data-pedido-sugerido="'.$this->pedido->id.'"', false)
        ->assertSee('Confirmar chegada do estoque')
        ->assertSee('Assinatura de recebimento');

    $this->get(route('radarmind.inventory.index'))
        ->assertSee(route('pedidos-reposicao.index', ['pedido' => $this->pedido->id]), false);
});

it('never adds the same replenishment twice', function () {
    $this->actingAs($this->matriz)->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Atendido', 'assinante' => 'Carlos Souza'])->assertOk();

    foreach (['Atendido', 'Em aberto', 'Cancelado'] as $status) {
        $this->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => $status, 'assinante' => 'Carlos Souza'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    // Também não dá para editar nem excluir o pedido que já chegou
    $this->putJson(route('pedidos-reposicao.update', $this->pedido), [
        'solicitante' => 'Ana', 'data_pedido' => '2026-11-09', 'unit_id' => $this->sub01->id,
        'item_id' => $this->item->id, 'quantidade' => 99, 'responsavel_nome' => 'Ana',
    ])->assertForbidden();
    $this->deleteJson(route('pedidos-reposicao.destroy', $this->pedido))->assertForbidden();

    expect($this->item->fresh()->quantity)->toBe(15)
        ->and(StockMovement::where('notes', 'like', "Reposição do pedido #{$this->pedido->id} atendida.%")->count())->toBe(1);

    $this->getJson(route('pedidos-reposicao.listar'))
        ->assertJsonPath('data.0.estoque_lancado', true)
        ->assertJsonPath('data.0.pode_mudar_status', false)
        ->assertJsonPath('data.0.pode_editar', false);
});

it('does not touch the stock when the order is cancelled', function () {
    $this->actingAs($this->matriz)->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Cancelado'])->assertOk();

    expect($this->item->fresh()->quantity)->toBe(3)
        ->and($this->pedido->fresh()->estoque_lancado_em)->toBeNull()
        ->and(Alerta::count())->toBe(0);
});

it('refuses to attend an order that is not linked to a stock item', function () {
    $pedidoSemItem = PedidoReposicao::create([
        'solicitante' => 'Ana', 'unit_id' => $this->sub01->id, 'data_pedido' => '2026-11-09',
        'item' => 'Adubo (texto livre)', 'quantidade' => 5, 'responsavel_nome' => 'Ana',
        'unidade' => 'Subempresa 01', 'status' => 'Em aberto',
    ]);

    $this->actingAs($this->matriz)
        ->patchJson(route('pedidos-reposicao.status', $pedidoSemItem), ['status' => 'Atendido', 'assinante' => 'Carlos Souza'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', "O pedido #{$pedidoSemItem->id} não está ligado a um item do estoque, então a chegada não pode ser lançada.");

    expect($pedidoSemItem->fresh()->status)->toBe('Em aberto');
});

it('updates the stock, the movements and the matriz dashboard when the arrival is confirmed', function () {
    $this->actingAs($this->matriz)
        ->from(route('radarmind.inventory.index'))
        ->patch(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Atendido', 'assinante' => 'Carlos Souza'])
        ->assertRedirect(route('radarmind.inventory.index'))
        ->assertSessionHas('success');

    expect($this->item->fresh()->quantity)->toBe(15);

    // Dashboard da matriz: o pedido sai dos "em aberto" (ficam os 2 do seeder) e a entrada aparece nas movimentações
    $this->get(route('matriz'))
        ->assertOk()
        ->assertViewHas('pedidos', fn ($pedidos) => $pedidos->count() === 2)
        ->assertViewHas('movimentacoes', fn ($movimentacoes) => $movimentacoes->first()['item'] === 'Correia dentada'
            && $movimentacoes->first()['tipo'] === 'entrada'
            && $movimentacoes->first()['quantidade'] === 12);
});

it('lets the subempresa confirm the arrival of its own order, with the same stock update, signer and alert', function () {
    $this->actingAs($this->subempresa)
        ->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Atendido', 'assinante' => 'João da Subempresa'])
        ->assertOk()
        ->assertJsonPath('mensagem', 'Chegada confirmada por João da Subempresa: 12 unidade(s) entraram no estoque de PEC-0210 · Correia dentada.');

    expect($this->item->fresh()->quantity)->toBe(15)
        ->and($this->pedido->fresh()->assinante_chegada)->toBe('João da Subempresa')
        ->and(StockMovement::latest('id')->first()->user_id)->toBe($this->subempresa->id);

    // A matriz fica sabendo: o alerta aparece para ela também
    $this->actingAs($this->matriz)->getJson(route('alertas.listar', ['gravidade' => 'CONCLUIDO']))
        ->assertJsonPath('data.0.titulo', 'Pedido de estoque atendido para Subempresa 01')
        ->assertJsonPath('data.0.detalhes.assinado_por', 'João da Subempresa');
});

it('shows the "Confirmar chegada" option to the subempresa only on its own open orders', function () {
    $this->actingAs($this->subempresa)->getJson(route('pedidos-reposicao.listar'))
        ->assertJsonPath('data.0.id', $this->pedido->id)
        ->assertJsonPath('data.0.pode_confirmar_chegada', true)
        ->assertJsonPath('data.0.pode_mudar_status', false);

    $this->actingAs($this->subempresa)->get(route('radarmind.inventory.index'))
        ->assertSee(route('pedidos-reposicao.index', ['pedido' => $this->pedido->id]), false);
});

it('keeps a subempresa from confirming the arrival of another unit order', function () {
    $outraSubempresa = User::factory()->create(['unit_id' => Unit::where('code', 'SUB02')->value('id')]);

    $this->actingAs($outraSubempresa)
        ->patchJson(route('pedidos-reposicao.status', $this->pedido), ['status' => 'Atendido', 'assinante' => 'Intruso'])
        ->assertForbidden();

    expect($this->item->fresh()->quantity)->toBe(3)
        ->and($this->pedido->fresh()->status)->toBe('Em aberto');
});
