<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estoque_id')->constrained('estoques')->cascadeOnDelete();
            $table->enum('tipo', ['entrada', 'saida']);
            $table->unsignedInteger('quantidade');
            $table->string('unidade');                       // "Matriz" ou nome da subempresa
            $table->string('destino')->nullable();
            $table->foreignId('os_id')->nullable()->constrained('ordens_servico')->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimentacoes');
    }
};
