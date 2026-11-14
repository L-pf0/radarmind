<?php

namespace App\Models;

use App\Models\Concerns\PertenceAUnidade;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\StockMovement;
use App\Observers\PedidoReposicaoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pedido de reposição de estoque (página "Pedidos de reposição", feita pelo grupo em Python).
 * É o único pedido de reposição do sistema: alimenta os dashboards, o Estoque e os Alertas.
 * Cada alteração pode gerar um alerta na Central de Notificações.
 */
#[ObservedBy(PedidoReposicaoObserver::class)]
class PedidoReposicao extends Model
{
    use PertenceAUnidade;

    protected $table = 'pedidos_reposicao';

    /** Enquanto está "Em aberto" a subempresa ainda pode editar/excluir; só a matriz muda o status. */
    public const STATUS = ['Em aberto', 'Atendido', 'Cancelado'];

    protected $fillable = [
        'solicitante',
        'unit_id',
        'data_pedido',
        'item',
        'item_id',
        'estoque_id',
        'quantidade',
        'responsavel_nome',
        'observacao',
        'status',
        'estoque_lancado_em',
        'assinante_chegada',
        'unidade',
        'user_id',
    ];

    protected $casts = [
        'data_pedido' => 'date',
        'quantidade' => 'integer',
        'estoque_lancado_em' => 'datetime',
    ];

    /** Item do estoque (a coluna "item" guarda o texto "código · nome"). */
    public function itemDoEstoque(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function estoque()
    {
        return $this->belongsTo(Estoque::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function estaEmAberto(): bool
    {
        return $this->status === 'Em aberto';
    }

    /**
     * Quem pode confirmar a chegada (marcar "Atendido"): a matriz em qualquer pedido,
     * a subempresa só nos pedidos da própria unidade. Só enquanto o estoque não foi lançado.
     */
    public function podeConfirmarChegada(User $user): bool
    {
        return ! $this->estoqueJaLancado()
            && ($user->ehMatriz() || $this->pertenceAUnidadeDe($user));
    }

    /**
     * A reposição já chegou e a quantidade já entrou no estoque: o pedido fica travado.
     */
    public function estoqueJaLancado(): bool
    {
        return $this->estoque_lancado_em !== null;
    }

    /**
     * Muda o status do pedido (a matriz em qualquer status; a subempresa só confirma a chegada).
     *
     * "Atendido" = a reposição chegou: exige o nome de quem assinou o recebimento ($assinante),
     * soma a quantidade ao estoque do item e registra a entrada em Movimentações. Tudo numa
     * transação, com o pedido e o item travados, para a mesma reposição nunca ser somada duas vezes.
     * O alerta "Pedido de estoque atendido" é criado pelo PedidoReposicaoObserver.
     *
     * @throws ValidationException
     */
    public function mudarStatus(string $novoStatus, User $responsavel, ?string $assinante = null): void
    {
        $assinante = trim((string) $assinante);

        if ($novoStatus === 'Atendido' && $assinante === '') {
            throw ValidationException::withMessages([
                'assinante' => 'Informe o nome de quem confirmou a chegada da reposição.',
            ]);
        }

        DB::transaction(function () use ($novoStatus, $responsavel, $assinante) {
            $pedido = static::lockForUpdate()->findOrFail($this->getKey());

            if ($pedido->estoqueJaLancado()) {
                throw ValidationException::withMessages([
                    'status' => "O pedido #{$pedido->id} já foi atendido e o estoque já foi atualizado; o status não pode mais mudar.",
                ]);
            }

            if ($novoStatus === 'Atendido') {
                $item = $pedido->item_id ? Item::lockForUpdate()->find($pedido->item_id) : null;

                if ($item === null) {
                    throw ValidationException::withMessages([
                        'status' => "O pedido #{$pedido->id} não está ligado a um item do estoque, então a chegada não pode ser lançada.",
                    ]);
                }

                $item->quantity += $pedido->quantidade;
                $item->save();

                StockMovement::create([
                    'item_id' => $item->id,
                    'unit_id' => $item->unit_id,
                    'type' => 'Entrada',
                    'quantity' => $pedido->quantidade,
                    'movement_date' => now()->toDateString(),
                    'notes' => "Reposição do pedido #{$pedido->id} atendida. Recebida por {$assinante}.",
                    'user_id' => $responsavel->id,
                ]);

                $pedido->estoque_lancado_em = now();
                $pedido->assinante_chegada = $assinante;
            }

            $pedido->status = $novoStatus;
            $pedido->save();

            $this->setRawAttributes($pedido->getAttributes(), true);
        });
    }
}
