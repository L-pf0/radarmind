<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Cria as unidades, as 2 contas de teste e o estoque inicial (matriz e filial).
     * Os eventos dos models ficam ligados de propósito: itens que já começam abaixo do mínimo
     * e máquinas em manutenção geram os alertas correspondentes na Central de Alertas.
     *
     * Para ter também movimentações, pedidos e O.S. de demonstração (usado nos testes):
     * php artisan db:seed --class=RadarMindSeeder
     */
    public function run(): void
    {
        // Unidades Matriz e Subempresa 01 (vazias)
        $this->call(UnidadesSeeder::class);

        // Contas de acesso: matriz@gmail.com e subempresa@gmail.com
        $this->call(UserSeeder::class);

        // Estoque: 100 itens da matriz (80 peças + 20 máquinas) e 70 da filial (60 peças + 10 máquinas)
        $this->call(EstoqueSeeder::class);
    }
}
