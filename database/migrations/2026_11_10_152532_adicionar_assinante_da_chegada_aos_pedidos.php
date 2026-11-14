<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinante da chegada da reposição: quem confirmou que a mercadoria chegou
 * (informado no modal "Confirmar chegada do estoque" ao marcar o pedido como Atendido).
 * - pedidos_reposicao.assinante_chegada: aparece na coluna "Assinante" dos pedidos registrados;
 * - alertas.assinado_por: aparece no alerta "Pedido de estoque atendido".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_reposicao', function (Blueprint $table) {
            $table->string('assinante_chegada', 120)->nullable()->after('estoque_lancado_em');
        });

        Schema::table('alertas', function (Blueprint $table) {
            $table->string('assinado_por', 120)->nullable()->after('qtd_recebida');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_reposicao', function (Blueprint $table) {
            $table->dropColumn('assinante_chegada');
        });

        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn('assinado_por');
        });
    }
};
