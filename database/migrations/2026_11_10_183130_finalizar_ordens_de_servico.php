<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O.S. "Feita": guarda quando a matriz finalizou a ordem (bloco "Finalizadas" da lista).
 * O status "Em andamento" deixou de existir: as O.S. que estavam nele passam a "Autorizada".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->timestamp('finalizada_em')->nullable()->after('status');
        });

        DB::table('ordens_servico')->where('status', 'Em andamento')->update(['status' => 'Autorizada']);
    }

    public function down(): void
    {
        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->dropColumn('finalizada_em');
        });
    }
};
