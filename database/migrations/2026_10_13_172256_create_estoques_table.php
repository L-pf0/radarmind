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
        Schema::create('estoques', function (Blueprint $table) {
            $table->id();
            $table->enum('categoria', ['peca', 'maquinario']);
            $table->string('codigo')->unique();
            $table->string('nome');
            $table->string('tipo_maquinario')->nullable();   // trator, colheitadeira, pulverizador...
            $table->unsignedSmallInteger('ano')->nullable();
            $table->decimal('horimetro', 10, 1)->nullable();
            $table->string('modelo_aplicacao')->nullable();
            $table->unsignedInteger('quantidade')->default(0);
            $table->unsignedInteger('quantidade_minima')->default(0);
            $table->string('localizacao')->nullable();       // corredor, prateleira
            $table->string('lote')->nullable();
            $table->string('numero_serie')->nullable();
            $table->boolean('alto_giro')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estoques');
    }
};
