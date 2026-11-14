<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central de Notificações / Alertas (módulo feito pelo grupo em Python).
 *
 * Mesmo formato do database.sql do módulo, com duas adaptações para o sistema:
 * - unit_id (radarmind_units) no lugar de subempresa_id/tabela subempresas;
 * - item_id opcional, para o botão do alerta abrir a tela certa já com o item escolhido.
 *
 * Guarda só os dados: título, texto, categoria, gravidade e botão vêm de Alerta::EVENTOS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_evento', 30);
            $table->foreignId('unit_id')->constrained('radarmind_units')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('radarmind_items')->nullOnDelete();
            $table->string('item_nome', 160)->nullable();
            $table->string('item_codigo', 50)->nullable();
            $table->boolean('alto_giro')->default(false);
            $table->integer('qtd_atual')->nullable();
            $table->integer('qtd_minima')->nullable();
            // número do pedido ou da O.S. na tela de destino
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->dateTime('data_criacao')->useCurrent();
            // vale para o alerta inteiro (não é por usuário)
            $table->boolean('lido')->default(false);

            $table->index(['unit_id', 'data_criacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
