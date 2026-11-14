<?php

return [
    /*
    | Layout Blade usado por todas as telas do módulo operacional
    | (estoque, movimentações, alertas, O.S. das unidades e relatórios).
    | O layout padrão usa a mesma navbar do restante do sistema.
    */
    'layout' => env('RADARMIND_LAYOUT', 'radarmind.layouts.app'),

    /*
    | Contas criadas pela seed (database/seeders/UserSeeder.php).
    | As senhas ficam só no .env (que não vai para o Git), nunca no código.
    | Sem a senha no .env, a seed cria uma senha aleatória e avisa no terminal.
    */
    'contas' => [
        ['email' => 'matriz@gmail.com', 'nome' => 'Matriz', 'tipo' => 'matriz', 'unidade' => 'MAT', 'senha' => env('SEED_SENHA_MATRIZ')],
        ['email' => 'subempresa01@gmail.com', 'nome' => 'Subempresa 01', 'tipo' => 'subempresa', 'unidade' => 'SUB01', 'senha' => env('SEED_SENHA_SUB01')],
        ['email' => 'subempresa02@gmail.com', 'nome' => 'Subempresa 02', 'tipo' => 'subempresa', 'unidade' => 'SUB02', 'senha' => env('SEED_SENHA_SUB02')],
        ['email' => 'subempresa03@gmail.com', 'nome' => 'Subempresa 03', 'tipo' => 'subempresa', 'unidade' => 'SUB03', 'senha' => env('SEED_SENHA_SUB03')],
        ['email' => 'subempresa04@gmail.com', 'nome' => 'Subempresa 04', 'tipo' => 'subempresa', 'unidade' => 'SUB04', 'senha' => env('SEED_SENHA_SUB04')],
    ],
];
