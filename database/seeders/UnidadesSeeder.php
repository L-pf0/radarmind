<?php

namespace Database\Seeders;

use App\Models\RadarMind\Unit;
use Illuminate\Database\Seeder;

/**
 * Unidades das contas: Matriz e Subempresas 01 a 04 (o estoque vem do EstoqueSeeder).
 * Pode rodar mais de uma vez sem duplicar.
 */
class UnidadesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'MAT', 'name' => 'Matriz'],
            ['code' => 'SUB01', 'name' => 'Subempresa 01'],
            ['code' => 'SUB02', 'name' => 'Subempresa 02'],
            ['code' => 'SUB03', 'name' => 'Subempresa 03'],
            ['code' => 'SUB04', 'name' => 'Subempresa 04'],
        ] as $unidade) {
            Unit::updateOrCreate(['code' => $unidade['code']], $unidade);
        }
    }
}
