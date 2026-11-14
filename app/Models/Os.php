<?php

namespace App\Models;

use App\Models\Concerns\PertenceAUnidade;
use App\Observers\OsObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Cada alteração pode gerar um alerta na Central de Notificações
#[ObservedBy(OsObserver::class)]
class Os extends Model
{
    // unit_id = unidade de quem registrou: a subempresa vê só as dela, a matriz vê todas
    use PertenceAUnidade;

    protected $table = 'ordens_servico';

    protected $fillable = [
        'solicitante',
        'maquina',
        'categoria',
        'tipo_problema',
        'descricao',
        'prioridade',
        'status',
        'finalizada_em',
        'user_id',
        'unit_id',
    ];

    protected $casts = [
        'finalizada_em' => 'datetime',
    ];

    /** Status final: a O.S. vai para o bloco "Finalizadas" e não muda mais. */
    public const FEITA = 'Feita';

    public const STATUS = [
        'Aguardando autorização',
        'Negada',
        'Autorizada',
        self::FEITA,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function movimentacoes()
    {
        return $this->hasMany(Movimentacao::class, 'os_id');
    }

    public function estaFeita(): bool
    {
        return $this->status === self::FEITA;
    }

    /**
     * O.S. ainda em aberto (lista principal).
     */
    public function scopeEmAberto(Builder $query): void
    {
        $query->where('status', '!=', self::FEITA);
    }

    /**
     * O.S. finalizadas (bloco "Finalizadas").
     */
    public function scopeFeitas(Builder $query): void
    {
        $query->where('status', self::FEITA);
    }

    /**
     * Troca o status; ao virar "Feita", registra quando foi finalizada.
     */
    public function mudarStatus(string $novoStatus): void
    {
        $this->status = $novoStatus;
        $this->finalizada_em = $novoStatus === self::FEITA ? now() : null;
        $this->save();
    }
}
