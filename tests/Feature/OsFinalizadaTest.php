<?php

use App\Models\Alerta;
use App\Models\Os;
use App\Models\RadarMind\Unit;
use App\Models\User;
use Database\Seeders\RadarMindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RadarMindSeeder::class);
    $this->sub01 = Unit::where('code', 'SUB01')->first();
    $this->sub02 = Unit::where('code', 'SUB02')->first();
    $this->subempresa = User::factory()->create(['unit_id' => $this->sub01->id]);
    $this->outraSubempresa = User::factory()->create(['unit_id' => $this->sub02->id]);
    $this->matriz = User::factory()->matriz()->create(['unit_id' => Unit::where('code', 'MAT')->value('id')]);

    $this->os = Os::create([
        'solicitante' => 'Carlos Souza', 'maquina' => 'MAQ-0011 · Plantadeira pneumática', 'categoria' => 'maquinario',
        'tipo_problema' => 'Hidráulico', 'descricao' => 'Vazamento na mangueira do sistema hidráulico.',
        'prioridade' => 'Alta', 'status' => 'Autorizada', 'user_id' => $this->subempresa->id, 'unit_id' => $this->sub01->id,
    ]);

    Alerta::query()->delete(); // só os alertas criados no próprio teste
});

it('offers "Feita" instead of "Em andamento" when the matriz edits the status', function () {
    $this->actingAs($this->matriz)->get(route('os.edit', $this->os))
        ->assertOk()
        ->assertSee('value="Feita"', false)
        ->assertDontSee('Em andamento');

    $this->put(route('os.status', $this->os), ['status' => 'Em andamento'])
        ->assertSessionHasErrors('status');
});

it('finalizes the O.S., records when and alerts the unit that requested it', function () {
    $this->actingAs($this->matriz)
        ->put(route('os.status', $this->os), ['status' => 'Feita'])
        ->assertRedirect(route('os.index'));

    expect($this->os->fresh())
        ->status->toBe('Feita')
        ->finalizada_em->not->toBeNull();

    $alerta = Alerta::sole();

    expect($alerta)
        ->tipo_evento->toBe('OS_FINALIZADA')
        ->unit_id->toBe($this->sub01->id)
        ->referencia_id->toBe($this->os->id);

    // A unidade que pediu vê o alerta; a outra subempresa não
    $this->actingAs($this->subempresa)->getJson(route('alertas.listar'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.titulo', "O.S. #{$this->os->id} finalizada para Subempresa 01")
        ->assertJsonPath('data.0.gravidade', 'CONCLUIDO')
        ->assertJsonPath('data.0.detalhes.solicitante', 'Carlos Souza')
        ->assertJsonPath('data.0.detalhes.assinado_por', null);

    $this->actingAs($this->outraSubempresa)->getJson(route('alertas.listar'))->assertJsonCount(0, 'data');
    $this->actingAs($this->matriz)->getJson(route('alertas.listar'))->assertJsonCount(1, 'data');
});

it('moves the finished O.S. to the "Finalizadas" block', function () {
    $this->os->mudarStatus('Feita');

    $this->actingAs($this->subempresa)->get(route('os.index'))
        ->assertOk()
        ->assertViewHas('ordens', fn ($ordens) => $ordens->isEmpty())
        ->assertViewHas('finalizadas', fn ($finalizadas) => $finalizadas->pluck('id')->all() === [$this->os->id])
        ->assertSeeInOrder(['Finalizadas', 'Plantadeira pneumática', 'Finalizada em']);

    $this->actingAs($this->outraSubempresa)->get(route('os.index'))
        ->assertViewHas('finalizadas', fn ($finalizadas) => $finalizadas->isEmpty());
});

it('does not let a finished O.S. change again (no repeated alert)', function () {
    $this->os->mudarStatus('Feita');
    Alerta::query()->delete();

    $this->actingAs($this->matriz)->get(route('os.edit', $this->os))->assertForbidden();
    $this->put(route('os.status', $this->os), ['status' => 'Autorizada'])->assertForbidden();

    expect($this->os->fresh()->status)->toBe('Feita')
        ->and(Alerta::count())->toBe(0);
});

it('keeps the subempresa from finishing an O.S.', function () {
    $this->actingAs($this->subempresa)
        ->put(route('os.status', $this->os), ['status' => 'Feita'])
        ->assertForbidden();

    expect($this->os->fresh()->status)->toBe('Autorizada');
});

it('does not alert when the status changes to something other than "Feita"', function () {
    $this->actingAs($this->matriz)->put(route('os.status', $this->os), ['status' => 'Negada']);

    expect(Alerta::count())->toBe(0)
        ->and($this->os->fresh()->finalizada_em)->toBeNull();
});
