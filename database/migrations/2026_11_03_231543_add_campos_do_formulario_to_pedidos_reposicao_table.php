<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integra o módulo "Pedidos de Reposição" (feito originalmente em Python/Flask)
 * à tabela pedidos_reposicao do Laravel.
 *
 * O formulário informa o item em texto livre, a data do pedido e o nome do
 * responsável; a unidade do responsável continua na coluna "unidade".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_reposicao', function (Blueprint $table) {
            $table->date('data_pedido')->nullable()->after('solicitante');
            $table->string('item', 160)->nullable()->after('data_pedido');
            $table->string('responsavel_nome', 120)->nullable()->after('quantidade');
            $table->unsignedBigInteger('estoque_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_reposicao', function (Blueprint $table) {
            $table->dropColumn(['data_pedido', 'item', 'responsavel_nome']);
            $table->unsignedBigInteger('estoque_id')->nullable(false)->change();
        });
    }
};
