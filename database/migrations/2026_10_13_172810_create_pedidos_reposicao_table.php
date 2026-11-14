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
        Schema::create('pedidos_reposicao', function (Blueprint $table) {
            $table->id();
            $table->string('solicitante');
            $table->foreignId('estoque_id')->constrained('estoques')->cascadeOnDelete();
            $table->unsignedInteger('quantidade');
            $table->string('unidade');                       // unidade do responsável
            $table->foreignId('user_id')->constrained();     // responsável pelo registro
            $table->timestamps();                            // created_at = data do pedido
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos_reposicao');
    }
};
