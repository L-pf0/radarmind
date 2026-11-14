<?php

namespace App\Observers;

use App\Models\PedidoReposicao;
use App\Support\GeradorDeAlertas;

/**
 * Pedido novo gera "Pedido de reposição"; pedido atendido pela matriz gera "Pedido aprovado".
 */
class PedidoReposicaoObserver
{
    public function __construct(private GeradorDeAlertas $alertas) {}

    public function created(PedidoReposicao $pedido): void
    {
        $this->alertas->pedidoCriado($pedido);
    }

    public function updated(PedidoReposicao $pedido): void
    {
        if ($pedido->wasChanged('status')) {
            $this->alertas->pedidoMudouDeStatus($pedido);
        }
    }
}
