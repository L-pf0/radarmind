<?php

namespace App\Support;

/**
 * Ponte de integração entre o módulo operacional e a autenticação do projeto.
 *
 * As rotas do módulo (routes/radarmind.php) exigem login, então o usuário
 * logado é gravado como responsável nas movimentações, pedidos e O.S.
 */
final class RadarMindIntegration
{
    public static function userId(): ?int
    {
        return auth()->id();
    }
}
