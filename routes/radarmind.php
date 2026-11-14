<?php

use App\Http\Controllers\RadarMind\InventoryController;
use App\Http\Controllers\RadarMind\MovementController;
use App\Http\Controllers\RadarMind\ReportController;
use App\Http\Controllers\RadarMind\ServiceOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RadarMind - módulo operacional
|--------------------------------------------------------------------------
| Incluído no final de routes/web.php. Todas as telas exigem login.
|
| Estoque e movimentações são de matriz E subempresa (os alertas ficam na
| Central de Notificações, em routes/web.php):
| - a matriz vê todas as unidades, a subempresa só a própria;
| - cada um só cadastra/edita estoque e movimentações da própria unidade
|   (a regra fica nos controllers, via Item::visivelPara / pertenceAUnidadeDe).
|
| Relatórios consolidados e as O.S. por maquinário do grupo são só da matriz.
|
| Todos os nomes de rota usam o prefixo "radarmind." para evitar colisões
| com rotas criadas por outros integrantes do projeto.
*/
Route::middleware('auth')->name('radarmind.')->group(function () {
    Route::get('/estoque', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/estoque', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/estoque/{item}/editar', [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('/estoque/{item}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/estoque/{item}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

    Route::get('/movimentacoes', [MovementController::class, 'index'])->name('movements.index');
    Route::post('/movimentacoes', [MovementController::class, 'store'])->name('movements.store');

    Route::middleware('perfil:matriz')->group(function () {
        Route::get('/ordens-servico', [ServiceOrderController::class, 'index'])->name('service-orders.index');
        Route::post('/ordens-servico', [ServiceOrderController::class, 'store'])->name('service-orders.store');
        Route::patch('/ordens-servico/{serviceOrder}/status', [ServiceOrderController::class, 'updateStatus'])->name('service-orders.status');
        Route::delete('/ordens-servico/{serviceOrder}', [ServiceOrderController::class, 'destroy'])->name('service-orders.destroy');

        Route::get('/relatorios', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/relatorios/visualizar', [ReportController::class, 'preview'])->name('reports.preview');
        Route::get('/relatorios/exportar', [ReportController::class, 'export'])->name('reports.export');
    });
});
