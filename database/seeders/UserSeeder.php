<?php

namespace Database\Seeders;

use App\Models\RadarMind\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Contas de acesso: a matriz e as subempresas 01 a 04 (lista em config/radarmind.php).
 * As senhas vêm do .env (SEED_SENHA_MATRIZ, SEED_SENHA_SUB01...) para não ficarem no código.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('radarmind.contas') as $conta) {
            $senha = $conta['senha'];

            if (blank($senha)) {
                // Sem senha no .env: cria uma aleatória (a conta não fica com senha conhecida)
                $senha = Str::password(16, symbols: false);
                $this->command?->warn("Sem senha no .env para {$conta['email']}: senha gerada {$senha}");
            }

            // updateOrCreate: se o e-mail já existir, atualiza em vez de duplicar
            // Hash::make: a senha nunca fica salva em texto puro no banco
            User::updateOrCreate(['email' => $conta['email']], [
                'name' => $conta['nome'],
                'password' => Hash::make($senha),
                'tipo' => $conta['tipo'],
                'unit_id' => Unit::where('code', $conta['unidade'])->value('id'),
            ]);
        }
    }
}
