<?php

namespace App\Models\RadarMind;

use App\Models\Concerns\PertenceAUnidade;
use App\Models\PedidoReposicao;
use App\Observers\ItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Cada alteração pode gerar um alerta na Central de Notificações
#[ObservedBy(ItemObserver::class)]
class Item extends Model
{
    use PertenceAUnidade;

    protected $table = 'radarmind_items';

    protected $fillable = [
        'unit_id', 'category', 'sku', 'name', 'image_path', 'machine_type', 'model_application', 'year',
        'hour_meter', 'location', 'quantity', 'minimum_quantity', 'lot', 'serial_number', 'status', 'turnover',
    ];

    protected $casts = ['year' => 'integer', 'hour_meter' => 'decimal:1', 'quantity' => 'integer', 'minimum_quantity' => 'integer'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function replenishments(): HasMany
    {
        return $this->hasMany(ReplenishmentRequest::class);
    }

    /** Pedidos de reposição deste item (página "Pedidos de reposição"). */
    public function pedidosReposicao(): HasMany
    {
        return $this->hasMany(PedidoReposicao::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function getAlertLevelAttribute(): string
    {
        if ($this->minimum_quantity <= 0) {
            return 'Normal';
        }
        if ($this->quantity < $this->minimum_quantity) {
            return 'Crítico';
        }
        if ($this->quantity <= (int) ceil($this->minimum_quantity * 1.25)) {
            return 'Baixo';
        }

        return 'Normal';
    }

    /**
     * Quanto falta repor para o item sair do alerta (voltar ao nível "Normal" do getAlertLevelAttribute).
     */
    public function quantidadeParaNormalizar(): int
    {
        if ($this->minimum_quantity <= 0) {
            return 0;
        }

        return max(0, (int) ceil($this->minimum_quantity * 1.25) + 1 - $this->quantity);
    }

    public function getPriorityAttribute(): string
    {
        if ($this->alert_level === 'Crítico' && $this->turnover === 'Alto giro') {
            return 'Urgente';
        }
        if ($this->alert_level === 'Crítico' || ($this->alert_level === 'Baixo' && $this->turnover === 'Alto giro')) {
            return 'Alta';
        }

        return 'Média';
    }
}
