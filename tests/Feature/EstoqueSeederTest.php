<?php

use App\Models\Alerta;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EstoqueSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->matriz = Unit::where('code', 'MAT')->first();
});

/**
 * @return array<string, int>
 */
function itensPorCategoria(string $codigoDaUnidade): array
{
    return Item::where('unit_id', Unit::where('code', $codigoDaUnidade)->value('id'))->get()->countBy('category')->all();
}

it('creates the matriz and the four subempresas, each one in its own unit', function () {
    expect(User::with('unidade')->get()->mapWithKeys(fn (User $usuario) => [$usuario->email => $usuario->tipo.' · '.$usuario->unidade->code])->sortKeys()->all())
        ->toBe([
            'matriz@gmail.com' => 'matriz · MAT',
            'subempresa01@gmail.com' => 'subempresa · SUB01',
            'subempresa02@gmail.com' => 'subempresa · SUB02',
            'subempresa03@gmail.com' => 'subempresa · SUB03',
            'subempresa04@gmail.com' => 'subempresa · SUB04',
        ]);
});

it('takes the passwords from the .env and never leaves an account with a known password when it is missing', function () {
    config(['radarmind.contas.1.senha' => 'senha-do-env-123', 'radarmind.contas.2.senha' => null]);

    $this->seed(UserSeeder::class);

    expect(Hash::check('senha-do-env-123', User::where('email', 'subempresa01@gmail.com')->value('password')))->toBeTrue()
        ->and(Hash::check('', User::where('email', 'subempresa02@gmail.com')->value('password')))->toBeFalse();
});

it('stocks each unit according to its operation', function (string $codigo, int $pecas, int $maquinas) {
    expect(itensPorCategoria($codigo))->toEqual(['peca' => $pecas, 'maq' => $maquinas]);
})->with([
    'matriz' => ['MAT', 80, 20],
    'grãos' => ['SUB01', 60, 10],
    'pulverização' => ['SUB02', 52, 6],
    'solo e plantio' => ['SUB03', 60, 7],
    'colheita' => ['SUB04', 53, 5],
]);

it('bases every branch item on an item of the matriz catalog', function () {
    $catalogo = Item::where('unit_id', $this->matriz->id)->pluck('name', 'sku');

    Item::where('unit_id', '!=', $this->matriz->id)->with('unit')->get()->each(function (Item $item) use ($catalogo) {
        $sufixo = '-'.str_replace('SUB', 'S', $item->unit->code);

        expect($item->sku)->toEndWith($sufixo)
            ->and($item->name)->toBe($catalogo[str($item->sku)->beforeLast($sufixo)->toString()]);
    });
});

it('gives each branch machine its own serial number', function () {
    $series = Item::where('category', 'maq')->pluck('serial_number');

    expect($series->duplicates())->toBeEmpty();
});

it('fills every field that the stock form requires', function () {
    $obrigatorios = ['category', 'sku', 'name', 'location', 'lot', 'serial_number', 'status', 'turnover'];

    $incompletos = Item::all()->filter(fn (Item $item) => collect($obrigatorios)->contains(fn (string $campo) => blank($item->{$campo})));

    expect($incompletos->pluck('sku')->all())->toBe([])
        ->and(Item::where('category', 'peca')->whereNotNull('machine_type')->exists())->toBeFalse()
        ->and(Item::where('category', 'maq')->get()->every(fn (Item $item) => $item->machine_type && $item->model_application && $item->year && $item->quantity === 1))->toBeTrue();
});

it('creates only the alerts chosen for each unit', function () {
    $alertasPorUnidade = Alerta::with('unit')->get()->countBy(fn (Alerta $alerta) => $alerta->unit->code)->sortKeys()->all();

    expect($alertasPorUnidade)->toBe(['MAT' => 13, 'SUB01' => 8, 'SUB02' => 5, 'SUB03' => 6, 'SUB04' => 4])
        ->and(Alerta::where('tipo_evento', 'MAQUINARIO_QUEBRADO')->count())->toBe(5);
});

it('can run again without duplicating items or alerts', function () {
    $this->seed(EstoqueSeeder::class);

    expect(Item::count())->toBe(353)
        ->and(Alerta::count())->toBe(36);
});
