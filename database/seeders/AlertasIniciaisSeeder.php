<?php

namespace Database\Seeders;

use App\Models\Alerta;
use App\Models\Os;
use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;
use App\Support\GeradorDeAlertas;
use Illuminate\Database\Seeder;

/**
 * Gera os alertas da situação ATUAL do banco (estoque baixo/crítico, máquinas paradas,
 * pedidos em aberto e O.S. aguardando), para a Central de Notificações não começar vazia
 * em um banco que já tinha dados antes do módulo de alertas.
 *
 * Não apaga nem altera nada: só roda se a tabela de alertas estiver vazia.
 * php artisan db:seed --class=AlertasIniciaisSeeder
 */
class AlertasIniciaisSeeder extends Seeder
{
    public function run(GeradorDeAlertas $alertas): void
    {
        if (Alerta::exists()) {
            $this->command?->info('A tabela de alertas já tem dados: nada foi criado.');

            return;
        }

        Item::where('status', '!=', 'Inativo')->each(function (Item $item) use ($alertas) {
            $alertas->estoqueMudou($item, 'Normal');
            $alertas->maquinarioMudouDeStatus($item, 'Ativo');
        });

        PedidoReposicao::with('itemDoEstoque')->where('status', 'Em aberto')->each(
            fn (PedidoReposicao $pedido) => $alertas->pedidoCriado($pedido)
        );

        Os::where('status', 'Aguardando autorização')->each(fn (Os $os) => $alertas->osCriada($os));

        $this->command?->info(Alerta::count().' alerta(s) criado(s).');
    }
}
