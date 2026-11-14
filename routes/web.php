<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OsController;
use App\Http\Controllers\PedidoReposicaoController;
use App\Http\Controllers\SubempresaController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Página inicial: quem não entrou vai para o login; quem já entrou vai para o seu dashboard
Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->route(Auth::user()->ehMatriz() ? 'matriz' : 'subempresa');
});

// Só para quem NÃO está logado
Route::middleware('guest')->group(function () {
    // O nome 'login' é obrigatório: o Laravel manda para ele quem tenta abrir uma página protegida
    Route::get('/login', [AuthController::class, 'mostrarLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'autenticar'])->name('login.autenticar');
});

Route::middleware('auth')->get('/inicio', function () {
    return match (Auth::user()->tipo) {
        'matriz' => redirect('/matriz'),
        default => redirect('/subempresa'),   // troque pela URL do dashboard da subempresa
    };
})->name('home');

// Só para quem ESTÁ logado
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'sair'])->name('logout');

    // Dashboard da matriz: só perfil matriz (subempresa é mandada para o próprio dashboard)
    Route::get('/matriz', [DashboardController::class, 'matriz'])->middleware('perfil:matriz')->name('matriz');

    // Dashboard da subempresa (antes front-end + API Python separados): a tela + rotas JSON usadas pelo JS.
    // Tudo é filtrado pela unidade do usuário logado (users.unit_id).
    Route::middleware('perfil:subempresa')->group(function () {
        Route::get('/subempresa', [SubempresaController::class, 'index'])->name('subempresa');

        // Só leitura: os cadastros são feitos nas páginas do sistema (Estoque, Movimentações, O.S., Pedidos)
        Route::prefix('subempresa/api')->name('subempresa.')->group(function () {
            Route::get('/dashboard', [SubempresaController::class, 'dashboard'])->name('dashboard');
            Route::get('/estoque', [SubempresaController::class, 'estoque'])->name('estoque');
            Route::get('/movimentacoes', [SubempresaController::class, 'movimentacoes'])->name('movimentacoes');
            Route::get('/os', [SubempresaController::class, 'ordensServico'])->name('os');
            Route::get('/reposicoes', [SubempresaController::class, 'reposicoes'])->name('reposicoes');
        });
    });

    // As demais rotas (estoque, movimentações etc.) entram aqui dentro também

    // O.S: precisa de login porque o controller usa o usuário logado
    Route::prefix('os')->name('os.')->group(function () {

        // matriz e subempresa
        Route::get('/create', [OsController::class, 'create'])->name('create');
        Route::post('/', [OsController::class, 'store'])->name('store');

        // somente matriz
        Route::get('/', [OsController::class, 'index'])->name('index');
        Route::get('/{os}/edit', [OsController::class, 'edit'])->name('edit');
        Route::put('/{os}/status', [OsController::class, 'updateStatus'])->name('status');
    });

    // Pedidos de reposição (módulo que era em Python/Flask): a tela + rotas JSON usadas pelo JS.
    // É o único pedido de reposição do sistema (Estoque, Alertas e dashboards usam esta tabela).
    Route::prefix('pedidos-reposicao')->name('pedidos-reposicao.')->group(function () {
        Route::get('/', [PedidoReposicaoController::class, 'index'])->name('index');
        Route::get('/lista', [PedidoReposicaoController::class, 'listar'])->name('listar');
        Route::post('/', [PedidoReposicaoController::class, 'store'])->name('store');
        Route::put('/{pedido}', [PedidoReposicaoController::class, 'update'])->name('update');
        Route::delete('/{pedido}', [PedidoReposicaoController::class, 'destroy'])->name('destroy');

        // Status: a matriz muda qualquer pedido; a subempresa só confirma a chegada (Atendido) dos pedidos dela.
        // A regra fica no PedidoReposicaoController::atualizarStatus.
        Route::patch('/{pedido}/status', [PedidoReposicaoController::class, 'atualizarStatus'])->name('status');
    });

    // Central de Notificações / Alertas (módulo que era em Python/Flask): a tela + rotas JSON usadas pelo JS.
    // A matriz vê os alertas de todas as unidades; a subempresa, só os da própria unidade.
    Route::prefix('alertas')->name('alertas.')->group(function () {
        Route::get('/', [AlertaController::class, 'index'])->name('index');
        Route::get('/contexto', [AlertaController::class, 'contexto'])->name('contexto');
        Route::get('/lista', [AlertaController::class, 'listar'])->name('listar');
        // Notificação no canto da tela: alertas que chegaram desde a última consulta
        Route::get('/novos', [AlertaController::class, 'novos'])->name('novos');
        Route::patch('/{alerta}/lido', [AlertaController::class, 'marcarLido'])->name('lido');
        Route::post('/marcar-todos-lidos', [AlertaController::class, 'marcarTodosLidos'])->name('marcar-todos');
    });
});

// Estoque, movimentações, alertas, O.S. das unidades e relatórios (módulo do grupo)
require __DIR__.'/radarmind.php';

// Route::get('/subempresa', function () {
//     return view('dashboard.subempresa', [
//         // Dados da subempresa logada (status: 'ativo' ou 'inativo')
//         'subempresa' => ['nome' => 'Fazenda Boa Vista', 'email' => 'boavista@radarmind.com', 'status' => 'ativo'],
//         'stats' => ['itens_estoque' => 128, 'movimentacoes' => 34, 'pedidos_abertos' => 2],
//         'alertas' => [
//             ['tipo' => 'danger', 'item' => 'Diesel S10', 'descricao' => 'abaixo do estoque mínimo', 'tempo' => 'há 2h'],
//         ],
//     ]);
// })->name('subempresa');

// cadastrar estoque:
// Route::get('/estoque/cadastrar', function () {
//     return view('estoque.cadastrar'); // resources/views/estoque/cadastrar.blade.php
// })->name('estoque.cadastrar');

// routes/web.php
// Route::get('/movimentacoes/cadastrar', function () {
//     return view('movimentacoes.cadastrar', [
//         'usuario' => 'Maria da Silva',
//         'itens' => [
//             ['id' => 1, 'nome' => 'Filtro de óleo', 'sku' => 'PC-00123', 'disponivel' => 10],
//             ['id' => 2, 'nome' => 'Correia do motor', 'sku' => 'PC-00456', 'disponivel' => 4],
//         ],
//         'subempresas' => [
//             ['id' => 1, 'nome' => 'Fazenda Boa Vista'],
//             ['id' => 2, 'nome' => 'Fazenda Santa Luzia'],
//         ],
//     ]);
// })->name('movimentacoes.cadastrar');

// routes/web.php
// Route::get('/estoque/{id}/editar', function ($id) {
//     return view('estoque.editar', [
//         'item' => [
//             'id' => $id,
//             'categoria' => 'peca', // troque por 'maquinario' para testar o outro formulário
//             'sku' => 'PC-00123',
//             'nome' => 'Filtro de óleo do motor',
//             'tipo' => '',
//             'ano' => '',
//             'horimetro' => 1250,
//             'modelo' => 'Serve no trator 7230J',
//             'lote' => 'L2025-08',
//             'numero_serie' => 'SN-9843201',
//             'localizacao' => 'Corredor 3, prateleira B',
//             'quantidade' => 10,
//             'quantidade_minima' => 3,
//             'alto_giro' => true,
//         ],
//     ]);
// })->name('estoque.editar');

// routes/web.php
// Route::get('/estoque', function () {
//     return view('estoque.index', [
//         'itens' => [
//             ['id' => 1, 'categoria' => 'peca', 'nome' => 'Filtro de óleo do motor', 'sku' => 'PC-00123', 'quantidade' => 10, 'quantidade_minima' => 3, 'alto_giro' => true],
//             ['id' => 2, 'categoria' => 'peca', 'nome' => 'Correia do motor', 'sku' => 'PC-00456', 'quantidade' => 2, 'quantidade_minima' => 4, 'alto_giro' => false],
//             ['id' => 3, 'categoria' => 'maquinario', 'nome' => 'Trator John Deere 7230J', 'sku' => 'MQ-00001', 'quantidade' => 1, 'quantidade_minima' => 1, 'alto_giro' => false],
//             ['id' => 4, 'categoria' => 'peca', 'nome' => 'Bico pulverizador', 'sku' => 'PC-00789', 'quantidade' => 48, 'quantidade_minima' => 10, 'alto_giro' => true],
//             ['id' => 5, 'categoria' => 'maquinario', 'nome' => 'Colheitadeira Case 7150', 'sku' => 'MQ-00002', 'quantidade' => 2, 'quantidade_minima' => 1, 'alto_giro' => false],
//             ['id' => 6, 'categoria' => 'peca', 'nome' => 'Pneu traseiro 18.4-34', 'sku' => 'PC-01020', 'quantidade' => 6, 'quantidade_minima' => 2, 'alto_giro' => false],
//             ['id' => 7, 'categoria' => 'peca', 'nome' => 'Filtro de ar primário', 'sku' => 'PC-01101', 'quantidade' => 15, 'quantidade_minima' => 5, 'alto_giro' => true],
//             ['id' => 8, 'categoria' => 'maquinario', 'nome' => 'Pulverizador Jacto Uniport', 'sku' => 'MQ-00003', 'quantidade' => 1, 'quantidade_minima' => 1, 'alto_giro' => false],
//             ['id' => 9, 'categoria' => 'peca', 'nome' => 'Mangueira hidráulica', 'sku' => 'PC-01230', 'quantidade' => 0, 'quantidade_minima' => 3, 'alto_giro' => false],
//         ],
//     ]);
// })->name('estoque');

// Route::get('/movimentacoes', function () {
//     return view('movimentacoes.index', [
//         'movimentacoes' => [
//             [
//                 'id' => 1,
//                 'data' => now()->subHours(2),
//                 'item' => 'Filtro de óleo do motor',
//                 'sku' => 'PC-00123',
//                 'quantidade' => 3,
//                 'unidade_tipo' => 'subempresa',
//                 'subempresa' => 'Fazenda Boa Vista',
//                 'destino' => 'Colheitadeira Case 7150',
//                 'motivo' => 'Troca de filtro na colheitadeira',
//                 'usuario' => 'Maria da Silva'
//             ],
//             [
//                 'id' => 2,
//                 'data' => now()->subHours(5),
//                 'item' => 'Correia do motor',
//                 'sku' => 'PC-00456',
//                 'quantidade' => 1,
//                 'unidade_tipo' => 'matriz',
//                 'destino' => 'Oficina',
//                 'motivo' => '',
//                 'usuario' => 'João Pereira'
//             ],
//             [
//                 'id' => 3,
//                 'data' => now()->subDay(),
//                 'item' => 'Bico pulverizador',
//                 'sku' => 'PC-00789',
//                 'quantidade' => 12,
//                 'unidade_tipo' => 'subempresa',
//                 'subempresa' => 'Fazenda Santa Luzia',
//                 'destino' => 'Pulverizador Jacto Uniport',
//                 'motivo' => 'Reposição antes da safra',
//                 'usuario' => 'Maria da Silva'
//             ],
//             [
//                 'id' => 4,
//                 'data' => now()->subDays(3),
//                 'item' => 'Mangueira hidráulica',
//                 'sku' => 'PC-01230',
//                 'quantidade' => 2,
//                 'unidade_tipo' => 'matriz',
//                 'destino' => 'Fazenda Boa Vista',
//                 'motivo' => 'Transferência',
//                 'usuario' => 'João Pereira'
//             ],
//         ],
//     ]);
// })->name('movimentacoes');

// Route::delete('/estoque/{id}', ...)->name('estoque.excluir');   arrumar rota de exclusão quando adcionar mais o beackend

// As rotas estoque.cadastrar, movimentacoes.cadastrar,
// os.registrar e reposicao.registrar ainda não existem.
// Os cards apontam para # até você criá-las.

// Como mo  inicio era só layout,
// isso não é problema — mas quando for implementar a autenticação de verdade,
// o padrão comum é ter mais essa rota:

// Route::post('/login', [AuthController::class, 'login']);
