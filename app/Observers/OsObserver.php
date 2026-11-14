<?php

namespace App\Observers;

use App\Models\Os;
use App\Support\GeradorDeAlertas;

/**
 * O.S. registrada (pela matriz ou subempresa) gera o alerta "O.S. aberta" para a unidade.
 * Quando a matriz marca como "Feita", a unidade que registrou recebe o alerta de O.S. finalizada.
 */
class OsObserver
{
    public function __construct(private GeradorDeAlertas $alertas) {}

    public function created(Os $os): void
    {
        $this->alertas->osCriada($os);
    }

    public function updated(Os $os): void
    {
        if ($os->wasChanged('status') && $os->estaFeita()) {
            $this->alertas->osFinalizada($os);
        }
    }
}
