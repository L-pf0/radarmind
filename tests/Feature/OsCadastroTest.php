<?php

use App\Models\Os;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dadosDaOs(array $alteracoes = []): array
{
    return $alteracoes + [
        'solicitante' => 'Maria da Silva',
        'maquina' => 'Colhedora 3200',
        'categoria' => 'maquinario',
        'tipo_problema' => 'Vazamento',
        'descricao' => 'Vazamento de óleo no sistema hidráulico.',
        'prioridade' => 'Alta',
    ];
}

it('saves the category and a typed problem type', function () {
    $matriz = User::factory()->matriz()->create();

    $this->actingAs($matriz)
        ->post(route('os.store'), dadosDaOs())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('os.index'));

    expect(Os::first())
        ->categoria->toBe('maquinario')
        ->tipo_problema->toBe('Vazamento')
        ->status->toBe('Aguardando autorização');
});

it('shows the saved category on the O.S list', function () {
    $matriz = User::factory()->matriz()->create();
    Os::create(dadosDaOs(['categoria' => 'peca', 'status' => 'Aguardando autorização', 'user_id' => $matriz->id]));

    $this->actingAs($matriz)
        ->get(route('os.index'))
        ->assertOk()
        ->assertSee('Peça');
});

it('rejects an O.S without a valid category', function (array $dados) {
    $this->actingAs(User::factory()->create())
        ->post(route('os.store'), $dados)
        ->assertSessionHasErrors('categoria');

    expect(Os::count())->toBe(0);
})->with([
    'sem categoria' => [dadosDaOs(['categoria' => ''])],
    'categoria inexistente' => [dadosDaOs(['categoria' => 'veiculo'])],
]);
