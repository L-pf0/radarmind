<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertaResource;
use App\Models\Alerta;
use App\Models\RadarMind\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Central de Notificações / Alertas (antes um backend Python/Flask separado).
 *
 * - A matriz vê os alertas de todas as unidades (com filtro por unidade).
 * - A subempresa vê só os alertas da própria unidade, mesmo que peça outra.
 * - A página não altera pedidos nem O.S.: os botões levam para essas telas.
 *   A única coisa gravada aqui é o alerta marcado como lido.
 */
class AlertaController extends Controller
{
    public function index(): View
    {
        return view('alertas.index');
    }

    /**
     * Quem está logado e o que a tela deve mostrar (badge, texto, filtro de unidade).
     */
    public function contexto(Request $request): JsonResponse
    {
        $user = $request->user();
        $matriz = $user->ehMatriz();
        $nomeDaUnidade = $user->unidade?->name ?? $user->name;

        return response()->json([
            'usuario' => [
                'perfil' => $matriz ? 'MATRIZ' : 'FILIAL',
                'subempresa_id' => $user->unit_id,
                'nome' => $nomeDaUnidade,
            ],
            'badge' => $matriz ? 'Visão Matriz (Admin)' : "{$nomeDaUnidade} - Operacional",
            'subtitulo' => $matriz
                ? 'Acompanhe alertas e notificações de todo o grupo corporativo.'
                : 'Acompanhe os alertas operacionais da sua unidade.',
            // só a matriz recebe a lista de unidades (a subempresa não tem o filtro)
            'subempresas' => $matriz ? Unit::orderBy('name')->get(['id', 'name as nome']) : [],
        ]);
    }

    public function listar(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'subempresa_id' => ['nullable', 'integer'],
            'gravidade' => ['nullable', Rule::in(Alerta::GRAVIDADES)],
            'categoria' => ['nullable', Rule::in(Alerta::CATEGORIAS)],
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'gravidade.in' => 'Gravidade inválida.',
            'categoria.in' => 'Categoria inválida.',
            'data_inicio.date_format' => 'Data inválida. Use o formato AAAA-MM-DD.',
            'data_fim.date_format' => 'Data inválida. Use o formato AAAA-MM-DD.',
        ]);

        $user = $request->user();

        $alertas = Alerta::with('unit')
            ->visivelPara($user)
            ->whereIn('tipo_evento', array_keys(Alerta::EVENTOS))
            // o filtro de unidade só vale para a matriz
            ->when($user->ehMatriz() && ! empty($filtros['subempresa_id']), fn ($q) => $q->where('unit_id', $filtros['subempresa_id']))
            ->when($filtros['gravidade'] ?? null, fn ($q, $valor) => $q->comCampoDoModelo('gravidade', $valor))
            ->when($filtros['categoria'] ?? null, fn ($q, $valor) => $q->comCampoDoModelo('categoria', $valor))
            ->when($filtros['data_inicio'] ?? null, fn ($q, $valor) => $q->where('data_criacao', '>=', "{$valor} 00:00:00"))
            ->when($filtros['data_fim'] ?? null, fn ($q, $valor) => $q->where('data_criacao', '<=', "{$valor} 23:59:59"))
            ->orderByDesc('data_criacao')
            ->orderByDesc('id')
            ->get();

        return AlertaResource::collection($alertas);
    }

    /**
     * Alertas que chegaram desde a última consulta, para a notificação no canto da tela.
     *
     * O último alerta já notificado fica na sessão: na primeira consulta depois do login só
     * marcamos o ponto de partida (os alertas antigos não viram notificação). Como fica na
     * sessão, um alerta criado por um formulário aparece na página seguinte ao redirecionamento.
     */
    public function novos(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $chave = 'alertas.ultimo_notificado';

        $visiveis = Alerta::visivelPara($user)->whereIn('tipo_evento', array_keys(Alerta::EVENTOS));
        $ultimoNotificado = $request->session()->get($chave);

        if ($ultimoNotificado === null) {
            $request->session()->put($chave, (int) (clone $visiveis)->max('id'));

            return AlertaResource::collection(collect())->additional(['total' => 0]);
        }

        $novos = (clone $visiveis)->where('id', '>', $ultimoNotificado);
        $total = (clone $novos)->count();

        // No máximo 3 notificações por vez; o total avisa quantos chegaram ao todo
        $alertas = $novos->with('unit')->orderByDesc('id')->limit(3)->get();

        if ($alertas->isNotEmpty()) {
            $request->session()->put($chave, $alertas->max('id'));
        }

        return AlertaResource::collection($alertas)->additional(['total' => $total]);
    }

    public function marcarLido(Request $request, Alerta $alerta): JsonResponse
    {
        // Subempresa só mexe nos alertas da própria unidade (404, como no módulo original)
        abort_unless($alerta->podeSerVistoPor($request->user()), 404, 'Alerta não encontrado.');

        $alerta->update(['lido' => true]);

        return response()->json(['ok' => true]);
    }

    /**
     * Marca como lidos todos os alertas que o perfil enxerga (na matriz: de todas as unidades).
     */
    public function marcarTodosLidos(Request $request): JsonResponse
    {
        Alerta::visivelPara($request->user())->where('lido', false)->update(['lido' => true]);

        return response()->json(['ok' => true]);
    }
}
