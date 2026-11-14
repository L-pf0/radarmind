<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organiza o acesso por unidade:
 * - o usuário da matriz passa a ser da unidade "Matriz" (código MAT);
 * - a O.S. guarda a unidade de quem registrou (subempresa vê só as dela);
 * - pedidos_reposicao (página do grupo, antes em Python) vira o ÚNICO pedido de reposição:
 *   ganha unidade, item do estoque, status e observação, e recebe os pedidos que estavam
 *   em radarmind_replenishment_requests (a tabela antiga é mantida, nada é apagado).
 */
return new class extends Migration
{
    public function up(): void
    {
        $matrizId = DB::table('radarmind_units')->where('code', 'MAT')->value('id');

        if ($matrizId) {
            DB::table('users')->where('tipo', 'matriz')->whereNull('unit_id')->update(['unit_id' => $matrizId]);
        }

        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('user_id')
                ->constrained('radarmind_units')->nullOnDelete();
        });

        Schema::table('pedidos_reposicao', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('solicitante')
                ->constrained('radarmind_units')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->after('item')
                ->constrained('radarmind_items')->nullOnDelete();
            $table->text('observacao')->nullable()->after('responsavel_nome');
            $table->string('status', 30)->default('Em aberto')->after('observacao');
            // pedidos criados pelos alertas/importados podem não ter usuário
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        // Unidade das O.S. e pedidos que já existem = unidade de quem registrou
        $unidadePorUsuario = DB::table('users')->whereNotNull('unit_id')->pluck('unit_id', 'id');

        foreach ($unidadePorUsuario as $userId => $unitId) {
            DB::table('ordens_servico')->where('user_id', $userId)->whereNull('unit_id')->update(['unit_id' => $unitId]);
            DB::table('pedidos_reposicao')->where('user_id', $userId)->whereNull('unit_id')->update(['unit_id' => $unitId]);
        }

        $this->copiarPedidosDoModuloDeEstoque();
    }

    public function down(): void
    {
        Schema::table('pedidos_reposicao', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('item_id');
            $table->dropColumn(['observacao', 'status']);
        });

        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });
    }

    private function copiarPedidosDoModuloDeEstoque(): void
    {
        $antigos = DB::table('radarmind_replenishment_requests as r')
            ->join('radarmind_units as u', 'u.id', '=', 'r.unit_id')
            ->join('radarmind_items as i', 'i.id', '=', 'r.item_id')
            ->leftJoin('users', 'users.id', '=', 'r.user_id')
            ->select('r.*', 'u.name as unidade_nome', 'i.sku', 'i.name as item_nome', 'users.name as usuario_nome')
            ->get();

        foreach ($antigos as $antigo) {
            $responsavel = $antigo->usuario_nome ?? $antigo->unidade_nome;

            DB::table('pedidos_reposicao')->insert([
                'solicitante' => $responsavel,
                'unit_id' => $antigo->unit_id,
                'data_pedido' => $antigo->requested_at,
                'item' => "{$antigo->sku} · {$antigo->item_nome}",
                'item_id' => $antigo->item_id,
                'quantidade' => $antigo->quantity,
                'responsavel_nome' => $responsavel,
                'observacao' => $antigo->notes,
                'status' => $antigo->status,
                'unidade' => $antigo->unidade_nome,
                'user_id' => $antigo->user_id,
                'created_at' => $antigo->created_at,
                'updated_at' => $antigo->updated_at,
            ]);
        }
    }
};
