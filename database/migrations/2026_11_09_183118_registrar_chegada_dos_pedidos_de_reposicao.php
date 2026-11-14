<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido "Atendido" = a reposição chegou: a quantidade entra no estoque do item.
 * - pedidos_reposicao.estoque_lancado_em: quando a quantidade foi somada ao estoque
 *   (garante que a mesma reposição nunca é somada duas vezes);
 * - alertas.qtd_recebida: quantas unidades chegaram (usado no alerta "Pedido de estoque atendido").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_reposicao', function (Blueprint $table) {
            $table->dateTime('estoque_lancado_em')->nullable()->after('status');
        });

        Schema::table('alertas', function (Blueprint $table) {
            $table->integer('qtd_recebida')->nullable()->after('qtd_minima');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_reposicao', function (Blueprint $table) {
            $table->dropColumn('estoque_lancado_em');
        });

        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn('qtd_recebida');
        });
    }
};
