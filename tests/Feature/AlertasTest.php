<?php

use App\Models\Alerta;
use App\Models\Os;
use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\Unit;
use App\Models\User;
use Database\Seeders\RadarMindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RadarMindSeeder::class);
    $this->sub01 = Unit::where('code', 'SUB01')->first();
    $this->sub02 = Unit::where('code', 'SUB02')->first();
    $this->unidadeMatriz = Unit::where('code', 'MAT')->first();
    $this->subempresa = User::factory()->create(['unit_id' => $this->sub01->id]);
    $this->matriz = User::factory()->matriz()->create(['unit_id' => $this->unidadeMatriz->id]);
    Alerta::query()->delete(); // começa cada teste sem os alertas criados pelo seeder
});

// ===== O sistema cria os alertas sozinho =====

it('creates a critical stock alert when a movement takes the item below the minimum', function () {
    $item = Item::where('sku', 'FER-0012')->first(); // 6 / mín. 4

    $this->actingAs($this->subempresa)->post(route('radarmind.movements.store'), [
        'type' => 'Saída', 'item_id' => $item->id, 'quantity' => 3, 'movement_date' => now()->toDateString(),
    ]);

    expect(Alerta::sole())
        ->tipo_evento->toBe('ESTOQUE_CRITICO')
        ->unit_id->toBe($this->sub01->id)
        ->item_codigo->toBe('FER-0012')
        ->qtd_atual->toBe(3)
        ->qtd_minima->toBe(4);

    // Continuar crítico não repete o alerta
    $this->post(route('radarmind.movements.store'), [
        'type' => 'Saída', 'item_id' => $item->id, 'quantity' => 1, 'movement_date' => now()->toDateString(),
    ]);

    expect(Alerta::count())->toBe(1);
});

it('creates alerts for a new replenishment request and when the matriz attends it', function () {
    $item = Item::where('sku', 'PEC-0210')->first();

    $this->actingAs($this->subempresa)->postJson(route('pedidos-reposicao.store'), [
        'solicitante' => 'Ana', 'data_pedido' => '2026-11-09', 'unit_id' => $this->sub01->id,
        'item_id' => $item->id, 'quantidade' => 10, 'responsavel_nome' => 'Ana',
    ])->assertCreated();

    $pedido = PedidoReposicao::latest('id')->first();

    $this->actingAs($this->matriz)->patchJson(route('pedidos-reposicao.status', $pedido), ['status' => 'Atendido', 'assinante' => 'Carlos Souza'])->assertOk();

    expect(Alerta::orderBy('id')->pluck('tipo_evento')->all())->toBe(['PEDIDO_REPOSICAO', 'PEDIDO_APROVADO'])
        ->and(Alerta::first()->referencia_id)->toBe($pedido->id)
        ->and(Alerta::first()->unit_id)->toBe($this->sub01->id);
});

it('creates an alert when an O.S. is registered', function () {
    $this->actingAs($this->subempresa)->post(route('os.store'), [
        'solicitante' => 'Ana', 'maquina' => 'Plantadeira pneumática', 'categoria' => 'maquinario',
        'tipo_problema' => 'Hidráulico', 'descricao' => 'Vazamento na mangueira hidráulica.', 'prioridade' => 'Alta',
    ]);

    expect(Alerta::sole())
        ->tipo_evento->toBe('OS_ABERTA')
        ->unit_id->toBe($this->sub01->id)
        ->referencia_id->toBe(Os::first()->id);
});

it('creates alerts when a machine stops and when it goes back to work', function () {
    $maquina = Item::where('sku', 'MAQ-0011')->first();

    $maquina->update(['status' => 'Em manutenção']);
    $maquina->update(['status' => 'Ativo']);

    expect(Alerta::orderBy('id')->pluck('tipo_evento')->all())->toBe(['MAQUINARIO_QUEBRADO', 'MANUTENCAO_CONCLUIDA']);
});

it('lists the matriz own stock alert after it registers an item and moves it below the minimum', function () {
    // Mesmo passo a passo do teste manual: cadastrar o item e depois dar saídas
    $this->actingAs($this->matriz)->post(route('radarmind.inventory.store'), [
        'category' => 'peca', 'sku' => 'PC-00123', 'name' => 'Teste número 1', 'quantity' => 100,
        'minimum_quantity' => 10, 'status' => 'Ativo', 'turnover' => 'Alto giro',
        'location' => 'Corredor 1 · Prateleira A1', 'lot' => 'L2026-001', 'serial_number' => 'SN-0001',
    ])->assertSessionHasNoErrors();

    $item = Item::where('sku', 'PC-00123')->first();

    foreach ([90, 5] as $saida) {
        $this->post(route('radarmind.movements.store'), [
            'type' => 'Saída', 'item_id' => $item->id, 'quantity' => $saida, 'movement_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
    }

    $this->getJson(route('alertas.listar'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.titulo', 'Alerta de estoque crítico da Matriz')
        ->assertJsonPath('data.0.gravidade', 'CRITICO')
        ->assertJsonPath('data.1.titulo', 'Alerta de estoque baixo da Matriz');

    // A subempresa não vê os alertas da Matriz
    $this->actingAs($this->subempresa)->getJson(route('alertas.listar'))->assertJsonCount(0, 'data');
});

it('shows a subempresa alert to the matriz and to that subempresa, but not to the other subempresa', function () {
    $outraSubempresa = User::factory()->create(['unit_id' => $this->sub02->id]);
    $item = Item::where('sku', 'FER-0012')->first(); // Subempresa 01, 6 / mín. 4

    $this->actingAs($this->subempresa)->post(route('radarmind.movements.store'), [
        'type' => 'Saída', 'item_id' => $item->id, 'quantity' => 3, 'movement_date' => now()->toDateString(),
    ]);

    $this->actingAs($this->matriz)->getJson(route('alertas.listar'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subempresa_nome', 'Subempresa 01');
    $this->actingAs($this->subempresa)->getJson(route('alertas.listar'))->assertJsonCount(1, 'data');
    $this->actingAs($outraSubempresa)->getJson(route('alertas.listar'))->assertJsonCount(0, 'data');

    $this->actingAs($this->subempresa)->getJson(route('subempresa.dashboard'))->assertJsonCount(1, 'alertas');
    $this->actingAs($outraSubempresa)->getJson(route('subempresa.dashboard'))->assertJsonCount(0, 'alertas');
});

// ===== Quem vê o quê =====

function alertaDe(Unit $unidade, string $tipo = 'ESTOQUE_CRITICO', array $dados = []): Alerta
{
    return Alerta::create($dados + ['tipo_evento' => $tipo, 'unit_id' => $unidade->id, 'item_nome' => 'Item '.$unidade->name]);
}

it('shows every unit to the matriz and only the own unit to the subempresa', function () {
    alertaDe($this->sub01);
    alertaDe($this->sub02);
    alertaDe($this->unidadeMatriz);

    $this->actingAs($this->matriz)->getJson(route('alertas.listar'))->assertOk()->assertJsonCount(3, 'data');
    $this->getJson(route('alertas.listar', ['subempresa_id' => $this->sub02->id]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subempresa_nome', 'Subempresa 02');

    // A subempresa recebe só os dela, mesmo pedindo outra unidade
    $this->actingAs($this->subempresa)
        ->getJson(route('alertas.listar', ['subempresa_id' => $this->sub02->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subempresa_nome', 'Subempresa 01')
        ->assertJsonPath('data.0.titulo', 'Alerta de estoque crítico da unidade Subempresa 01');
});

it('filters by gravity, category and period, and rejects invalid filters', function () {
    alertaDe($this->sub01, 'ESTOQUE_CRITICO');
    alertaDe($this->sub01, 'PEDIDO_APROVADO', ['referencia_id' => 7]);
    alertaDe($this->sub01, 'OS_ABERTA', ['referencia_id' => 3]);

    $this->actingAs($this->matriz);
    $this->getJson(route('alertas.listar', ['gravidade' => 'CONCLUIDO']))->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.titulo', 'Pedido de estoque atendido para Subempresa 01');
    $this->getJson(route('alertas.listar', ['categoria' => 'MANUTENCAO']))->assertJsonCount(1, 'data');
    $this->getJson(route('alertas.listar', ['data_inicio' => now()->addDay()->toDateString()]))->assertJsonCount(0, 'data');

    $this->getJson(route('alertas.listar', ['gravidade' => 'XYZ']))->assertUnprocessable()
        ->assertJsonPath('errors.gravidade.0', 'Gravidade inválida.');
});

it('builds the action button with the real system pages for each profile', function () {
    $os = Os::create([
        'solicitante' => 'Ana', 'maquina' => 'Trator', 'categoria' => 'maquinario', 'tipo_problema' => 'Mecânico',
        'descricao' => 'Barulho estranho no motor.', 'prioridade' => 'Alta', 'status' => 'Aguardando autorização',
        'unit_id' => $this->sub01->id,
    ]);
    $item = Item::where('sku', 'PEC-0210')->first();
    alertaDe($this->sub01, 'ESTOQUE_BAIXO', ['item_id' => $item->id]);

    $this->actingAs($this->matriz)->getJson(route('alertas.listar', ['categoria' => 'MANUTENCAO']))
        ->assertJsonPath('data.0.redirecionamento.rotulo_botao', 'Autorizar O.S.')
        ->assertJsonPath('data.0.redirecionamento.url', route('os.edit', $os));

    $this->actingAs($this->subempresa)->getJson(route('alertas.listar', ['categoria' => 'MANUTENCAO']))
        ->assertJsonPath('data.0.redirecionamento.rotulo_botao', 'Acompanhar O.S.')
        ->assertJsonPath('data.0.redirecionamento.url', route('os.index'));

    $this->getJson(route('alertas.listar', ['categoria' => 'ESTOQUE']))
        ->assertJsonPath('data.0.redirecionamento.rotulo_botao', 'Solicitar Reposição')
        ->assertJsonPath('data.0.redirecionamento.url', route('pedidos-reposicao.index', ['item' => $item->id]));
});

// ===== Marcar como lido =====

it('marks as read only the alerts the profile can see', function () {
    $proprio = alertaDe($this->sub01);
    $deOutraUnidade = alertaDe($this->sub02);

    $this->actingAs($this->subempresa)->patchJson(route('alertas.lido', $proprio))->assertOk();
    $this->patchJson(route('alertas.lido', $deOutraUnidade))->assertNotFound();
    $this->postJson(route('alertas.marcar-todos'))->assertOk();

    expect($proprio->fresh()->lido)->toBeTrue()
        ->and($deOutraUnidade->fresh()->lido)->toBeFalse();

    // Na matriz, "marcar todos" vale para todas as unidades
    $this->actingAs($this->matriz)->postJson(route('alertas.marcar-todos'))->assertOk();

    expect(Alerta::where('lido', false)->count())->toBe(0);
});

// ===== Página e dashboards =====

it('renders the alerts page for both profiles', function (string $perfil) {
    $this->actingAs($this->{$perfil})
        ->get(route('alertas.index'))
        ->assertOk()
        ->assertSee('Notificações / Alertas')
        ->assertSee(route('alertas.listar'), false);

    $this->getJson(route('alertas.contexto'))
        ->assertOk()
        ->assertJsonPath('usuario.perfil', $perfil === 'matriz' ? 'MATRIZ' : 'FILIAL');
})->with(['matriz', 'subempresa']);

it('shows the alerts of every unit on the matriz dashboard and only the own ones on the subempresa dashboard', function () {
    alertaDe($this->sub01, 'ESTOQUE_CRITICO', ['item_nome' => 'Item da sub01']);
    alertaDe($this->sub02, 'ESTOQUE_CRITICO', ['item_nome' => 'Item da sub02']);

    $this->actingAs($this->matriz)->get(route('matriz'))
        ->assertOk()->assertSee('Item da sub01')->assertSee('Item da sub02');

    $this->actingAs($this->subempresa)->getJson(route('subempresa.dashboard'))
        ->assertOk()
        ->assertJsonCount(1, 'alertas')
        ->assertJsonPath('alertas.0.titulo', 'Alerta de estoque crítico da unidade Subempresa 01')
        ->assertJsonPath('alertasNaoLidos', 1);
});

// ===== Notificação de novos alertas (canto da tela) =====

it('notifies only the alerts that arrive after the first check, once each', function () {
    alertaDe($this->sub01, 'ESTOQUE_CRITICO', ['item_nome' => 'Alerta antigo']);

    // Primeira consulta só marca o ponto de partida: o alerta antigo não vira notificação
    $this->actingAs($this->matriz)->getJson(route('alertas.novos'))
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('total', 0);

    alertaDe($this->sub02, 'ESTOQUE_CRITICO', ['item_nome' => 'Correia nova']);

    $this->getJson(route('alertas.novos'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.gravidade', 'CRITICO')
        ->assertJsonPath('data.0.detalhes.item_nome', 'Correia nova');

    // Já notificado: não aparece de novo
    $this->getJson(route('alertas.novos'))->assertJsonCount(0, 'data');
});

it('sends at most three notifications at once and tells how many arrived', function () {
    $this->actingAs($this->matriz)->getJson(route('alertas.novos'));

    foreach (range(1, 5) as $numero) {
        alertaDe($this->sub01, 'ESTOQUE_BAIXO', ['item_nome' => "Item {$numero}"]);
    }

    $this->getJson(route('alertas.novos'))
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('total', 5)
        ->assertJsonPath('data.0.detalhes.item_nome', 'Item 5');

    $this->getJson(route('alertas.novos'))->assertJsonCount(0, 'data');
});

it('notifies the subempresa only about the alerts of its own unit', function () {
    $this->actingAs($this->subempresa)->getJson(route('alertas.novos'));

    alertaDe($this->sub02, 'ESTOQUE_CRITICO', ['item_nome' => 'Item da sub02']);
    alertaDe($this->sub01, 'ESTOQUE_CRITICO', ['item_nome' => 'Item da sub01']);

    $this->getJson(route('alertas.novos'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.detalhes.item_nome', 'Item da sub01');
});

it('puts the notification area on the internal pages', function () {
    $this->actingAs($this->subempresa)->get(route('radarmind.inventory.index'))
        ->assertOk()
        ->assertSee('id="rm-toasts"', false)
        ->assertSee(route('alertas.novos'), false)
        ->assertSee('data-url-central="'.route('alertas.index').'"', false);

    $this->actingAs($this->matriz)->get(route('matriz'))
        ->assertOk()->assertSee('id="rm-toasts"', false);
});
