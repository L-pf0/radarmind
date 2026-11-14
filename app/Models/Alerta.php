<?php

namespace App\Models;

use App\Models\Concerns\PertenceAUnidade;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerta da Central de Notificações (módulo feito pelo grupo em Python/Flask).
 *
 * A tabela guarda só os dados; título, texto, categoria, gravidade e botão de cada
 * tipo_evento vêm de EVENTOS (mesmo modelo do backend/app.py original).
 * A matriz vê os alertas de todas as unidades; a subempresa, só os da própria unidade.
 */
class Alerta extends Model
{
    use PertenceAUnidade;

    protected $table = 'alertas';

    const CREATED_AT = 'data_criacao';

    const UPDATED_AT = null;

    public const GRAVIDADES = ['CRITICO', 'ALERTA', 'CONCLUIDO'];

    public const CATEGORIAS = ['ESTOQUE', 'PEDIDOS', 'MAQUINARIO', 'MANUTENCAO'];

    /**
     * Um modelo por tipo_evento. No texto: {unidade}, {item} e {ref}.
     * Botão por perfil: [rótulo, nome da rota, parâmetros]. Nos parâmetros, ':campo' é trocado
     * pelo valor do alerta (ex.: ':item_id'). Sem o valor necessário, o botão usa a rota "reserva".
     *
     * @var array<string, array{categoria: string, gravidade: string, titulo: string, mensagem: string, mensagem_sem_assinante?: string, mensagem_sem_qtd?: string, botao: array<string, array{0: string, 1: string, 2: array<string, string>}>, reserva?: string}>
     */
    public const EVENTOS = [
        'ESTOQUE_CRITICO' => [
            'categoria' => 'ESTOQUE', 'gravidade' => 'CRITICO',
            'titulo' => 'Alerta de estoque crítico da {unidade}',
            'mensagem' => 'A {unidade} atingiu o limite mínimo de peças do item:',
            'botao' => [
                'MATRIZ' => ['Gerar Reposição', 'pedidos-reposicao.index', ['item' => ':item_id']],
                'FILIAL' => ['Solicitar Reposição', 'pedidos-reposicao.index', ['item' => ':item_id']],
            ],
        ],
        'ESTOQUE_BAIXO' => [
            'categoria' => 'ESTOQUE', 'gravidade' => 'ALERTA',
            'titulo' => 'Alerta de estoque baixo da {unidade}',
            'mensagem' => 'O estoque da {unidade} está se aproximando do mínimo no item:',
            'botao' => [
                'MATRIZ' => ['Gerar Reposição', 'pedidos-reposicao.index', ['item' => ':item_id']],
                'FILIAL' => ['Solicitar Reposição', 'pedidos-reposicao.index', ['item' => ':item_id']],
            ],
        ],
        'PEDIDO_REPOSICAO' => [
            'categoria' => 'PEDIDOS', 'gravidade' => 'ALERTA',
            'titulo' => 'Pedido #{ref} de reposição da {unidade}',
            'mensagem' => 'A {unidade} solicitou reposição de estoque do item:',
            'botao' => [
                'MATRIZ' => ['Aprovar Pedido', 'pedidos-reposicao.index', ['pedido' => ':referencia_id']],
                'FILIAL' => ['Confirmar Chegada', 'pedidos-reposicao.index', ['pedido' => ':referencia_id']],
            ],
        ],
        'PEDIDO_PENDENTE' => [
            'categoria' => 'PEDIDOS', 'gravidade' => 'CRITICO',
            'titulo' => 'Pedido #{ref} da {unidade} aguardando aprovação',
            'mensagem' => 'O pedido de reposição está aguardando análise administrativa:',
            'botao' => [
                'MATRIZ' => ['Aprovar Pedido', 'pedidos-reposicao.index', ['pedido' => ':referencia_id']],
                'FILIAL' => ['Confirmar Chegada', 'pedidos-reposicao.index', ['pedido' => ':referencia_id']],
            ],
        ],
        'PEDIDO_APROVADO' => [
            'categoria' => 'PEDIDOS', 'gravidade' => 'CONCLUIDO',
            // Pedido "Atendido" = a reposição chegou e a quantidade entrou no estoque
            'titulo' => 'Pedido de estoque atendido para {nome}',
            'mensagem' => 'O pedido #{ref} chegou e foi recebido por {assinante}: {qtd} unidade(s) entraram no estoque do item:',
            // alertas antigos, criados antes de o assinante / a quantidade recebida serem registrados
            'mensagem_sem_assinante' => 'O pedido #{ref} chegou: {qtd} unidade(s) entraram no estoque do item:',
            'mensagem_sem_qtd' => 'O pedido #{ref} de reposição foi atendido para o item:',
            'botao' => [
                'MATRIZ' => ['Ver Pedido', 'pedidos-reposicao.index', ['pedido' => ':referencia_id']],
                'FILIAL' => ['Ver Pedido', 'pedidos-reposicao.index', ['pedido' => ':referencia_id']],
            ],
        ],
        'OS_ABERTA' => [
            'categoria' => 'MANUTENCAO', 'gravidade' => 'ALERTA',
            'titulo' => 'O.S. #{ref} aberta na {unidade}',
            'mensagem' => 'Foi aberta uma ordem de serviço para o equipamento:',
            'botao' => [
                'MATRIZ' => ['Autorizar O.S.', 'os.edit', ['os' => ':referencia_id']],
                'FILIAL' => ['Acompanhar O.S.', 'os.index', []],
            ],
            'reserva' => 'os.index',
        ],
        'OS_FINALIZADA' => [
            'categoria' => 'MANUTENCAO', 'gravidade' => 'CONCLUIDO',
            // a matriz marcou a O.S. como "Feita"; vai para a unidade que registrou
            'titulo' => 'O.S. #{ref} finalizada para {nome}',
            'mensagem' => 'A matriz concluiu a ordem de serviço pedida por {assinante} para o equipamento:',
            'mensagem_sem_assinante' => 'A matriz concluiu a ordem de serviço do equipamento:',
            'botao' => [
                'MATRIZ' => ['Ver Finalizadas', 'os.index', []],
                'FILIAL' => ['Ver Finalizadas', 'os.index', []],
            ],
        ],
        'MAQUINARIO_QUEBRADO' => [
            'categoria' => 'MAQUINARIO', 'gravidade' => 'CRITICO',
            'titulo' => 'Maquinário parado na {unidade}: {item}',
            'mensagem' => 'O equipamento apresentou falha durante a operação:',
            'botao' => [
                'MATRIZ' => ['Gerar O.S.', 'os.create', ['maquina' => ':maquina']],
                'FILIAL' => ['Abrir Chamado', 'os.create', ['maquina' => ':maquina']],
            ],
        ],
        'MANUTENCAO_AVISO' => [
            'categoria' => 'MANUTENCAO', 'gravidade' => 'ALERTA',
            'titulo' => 'Aviso de manutenção de {item} na {unidade}',
            'mensagem' => 'Há uma manutenção programada para o equipamento:',
            'botao' => [
                'MATRIZ' => ['Gerar O.S.', 'os.create', ['maquina' => ':maquina']],
                'FILIAL' => ['Abrir Chamado', 'os.create', ['maquina' => ':maquina']],
            ],
        ],
        'MANUTENCAO_CONCLUIDA' => [
            'categoria' => 'MANUTENCAO', 'gravidade' => 'CONCLUIDO',
            'titulo' => 'Manutenção de {item} concluída na {unidade}',
            'mensagem' => 'A manutenção foi concluída e o equipamento voltou a operar:',
            'botao' => [
                'MATRIZ' => ['Ver O.S.', 'os.edit', ['os' => ':referencia_id']],
                'FILIAL' => ['Ver O.S.', 'os.index', []],
            ],
            'reserva' => 'os.index',
        ],
    ];

    protected $fillable = [
        'tipo_evento', 'unit_id', 'item_id', 'item_nome', 'item_codigo', 'alto_giro',
        'qtd_atual', 'qtd_minima', 'qtd_recebida', 'assinado_por', 'referencia_id', 'lido',
    ];

    protected $casts = [
        'alto_giro' => 'boolean',
        'lido' => 'boolean',
        'qtd_atual' => 'integer',
        'qtd_minima' => 'integer',
        'qtd_recebida' => 'integer',
        'data_criacao' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function itemDoEstoque(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * @return array{categoria: string, gravidade: string, titulo: string, mensagem: string, mensagem_sem_assinante?: string, mensagem_sem_qtd?: string, botao: array<string, array{0: string, 1: string, 2: array<string, string>}>, reserva?: string}|null
     */
    public function modelo(): ?array
    {
        return self::EVENTOS[$this->tipo_evento] ?? null;
    }

    /**
     * Tipos de evento que têm a gravidade/categoria pedida (gravidade e categoria vêm do modelo, não da tabela).
     */
    public function scopeComCampoDoModelo(Builder $query, string $campo, string $valor): void
    {
        $tipos = array_keys(array_filter(self::EVENTOS, fn (array $modelo) => $modelo[$campo] === $valor));

        $query->whereIn('tipo_evento', $tipos ?: ['__nenhum__']);
    }

    /**
     * Valores usados no título e na mensagem do modelo.
     *
     * @return array{unidade: string, nome: string, item: string, ref: string, qtd: string, assinante: string}
     */
    public function valoresDoTexto(): array
    {
        $unidade = $this->unit;

        return [
            'unidade' => $unidade?->code === 'MAT' ? 'Matriz' : 'unidade '.($unidade?->name ?? ''),
            // nome da unidade sem o "unidade" na frente (ex.: "Subempresa 01" ou "Matriz")
            'nome' => $unidade?->name ?? '',
            'item' => $this->item_nome ?: 'item',
            'ref' => (string) ($this->referencia_id ?? ''),
            'qtd' => (string) ($this->qtd_recebida ?? ''),
            'assinante' => (string) ($this->assinado_por ?? ''),
        ];
    }

    public function texto(string $chave): string
    {
        $valores = $this->valoresDoTexto();
        $modelo = $this->modelo();

        // Alertas antigos sem a quantidade recebida ou sem o assinante usam a mensagem que não depende deles
        if ($chave === 'mensagem' && $valores['qtd'] === '' && isset($modelo['mensagem_sem_qtd'])) {
            $chave = 'mensagem_sem_qtd';
        } elseif ($chave === 'mensagem' && $valores['assinante'] === '' && isset($modelo['mensagem_sem_assinante'])) {
            $chave = 'mensagem_sem_assinante';
        }

        return strtr($modelo[$chave] ?? '', [
            '{unidade}' => $valores['unidade'],
            '{nome}' => $valores['nome'],
            '{item}' => $valores['item'],
            '{ref}' => $valores['ref'],
            '{qtd}' => $valores['qtd'],
            '{assinante}' => $valores['assinante'],
        ]);
    }

    /**
     * "Há 20min", "Há 2h" (até 48h), "Há 5d", "Há 1m" (meses), "Há 1a" (anos), igual ao módulo original.
     */
    public function tempoRelativo(): string
    {
        $segundos = max(0, now()->getTimestamp() - $this->data_criacao->getTimestamp());
        $minutos = intdiv($segundos, 60);
        $horas = intdiv($segundos, 3600);
        $dias = intdiv($segundos, 86400);

        return match (true) {
            $minutos < 60 => 'Há '.max(1, $minutos).'min',
            $horas <= 48 => "Há {$horas}h",
            $dias < 30 => "Há {$dias}d",
            $dias < 365 => 'Há '.intdiv($dias, 30).'m',
            default => 'Há '.intdiv($dias, 365).'a',
        };
    }

    /**
     * Botão do alerta para o perfil do usuário (MATRIZ ou FILIAL), já com a URL da tela do sistema.
     *
     * @return array{rotulo: string, url: string}
     */
    public function botaoPara(User $user): array
    {
        $modelo = $this->modelo();
        [$rotulo, $rota, $parametros] = $modelo['botao'][$user->ehMatriz() ? 'MATRIZ' : 'FILIAL'];

        $valores = [
            ':item_id' => $this->item_id,
            ':referencia_id' => $this->referencia_id,
            ':maquina' => trim(($this->item_codigo ? "{$this->item_codigo} · " : '').($this->item_nome ?? '')) ?: null,
        ];

        // Só entram na URL os valores que o alerta tem
        $resolvidos = array_filter(
            array_map(fn (string $campo) => $valores[$campo] ?? null, $parametros),
            fn ($valor) => $valor !== null,
        );

        // Rota que exige o número (ex.: editar a O.S.) e o alerta não tem: usa a tela "reserva"
        if (count($resolvidos) < count($parametros) && isset($modelo['reserva'])) {
            return ['rotulo' => $rotulo, 'url' => route($modelo['reserva'])];
        }

        return ['rotulo' => $rotulo, 'url' => route($rota, $resolvidos)];
    }
}
