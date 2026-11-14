<?php

namespace App\Observers;

use App\Models\RadarMind\Item;
use App\Support\GeradorDeAlertas;

/**
 * Gera alertas de estoque (baixo/crítico) e de maquinário (parado/manutenção concluída)
 * sempre que um item é cadastrado, editado ou movimentado.
 */
class ItemObserver
{
    public function __construct(private GeradorDeAlertas $alertas) {}

    public function created(Item $item): void
    {
        $this->alertas->estoqueMudou($item, 'Normal');
        $this->alertas->maquinarioMudouDeStatus($item, 'Ativo');
    }

    public function updated(Item $item): void
    {
        if ($item->wasChanged(['quantity', 'minimum_quantity', 'status'])) {
            $this->alertas->estoqueMudou($item, $this->nivelAnterior($item));
        }

        if ($item->wasChanged('status')) {
            $this->alertas->maquinarioMudouDeStatus($item, $item->getOriginal('status'));
        }
    }

    /**
     * Nível de alerta que o item tinha antes desta alteração.
     */
    private function nivelAnterior(Item $item): string
    {
        $anterior = (new Item)->forceFill([
            'quantity' => $item->getOriginal('quantity'),
            'minimum_quantity' => $item->getOriginal('minimum_quantity'),
        ]);

        return $item->getOriginal('status') === 'Inativo' ? 'Normal' : $anterior->alert_level;
    }
}
