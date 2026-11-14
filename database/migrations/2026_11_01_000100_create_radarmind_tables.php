<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radarmind_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('radarmind_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('radarmind_units')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('category', 10);
            $table->string('sku', 50)->unique();
            $table->string('name', 160);
            $table->string('image_path')->nullable();
            $table->string('machine_type', 100)->nullable();
            $table->string('model_application')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->decimal('hour_meter', 12, 1)->nullable();
            $table->string('location', 160)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('minimum_quantity')->default(0);
            $table->string('lot', 100)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('status', 40)->default('Ativo');
            $table->string('turnover', 40)->default('Baixo giro');
            $table->timestamps();
        });

        Schema::create('radarmind_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('radarmind_items')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('radarmind_units')->restrictOnDelete();
            $table->string('type', 20);
            $table->unsignedInteger('quantity');
            $table->date('movement_date');
            $table->text('notes')->nullable();
            // Sem FK de propósito: a equipe de autenticação poderá ligar este campo
            // à tabela de usuários escolhida por ela sem conflito de migrations.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('radarmind_replenishment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('radarmind_units')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('radarmind_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('Em aberto');
            $table->date('requested_at');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('radarmind_service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('radarmind_items')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('radarmind_units')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('priority', 30)->default('Média');
            $table->string('status', 30)->default('Aberta');
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radarmind_service_orders');
        Schema::dropIfExists('radarmind_replenishment_requests');
        Schema::dropIfExists('radarmind_stock_movements');
        Schema::dropIfExists('radarmind_items');
        Schema::dropIfExists('radarmind_units');
    }
};
