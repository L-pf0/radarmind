<?php

namespace App\Http\Controllers\RadarMind;

use App\Http\Controllers\Controller;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\StockMovement;
use App\Models\RadarMind\Unit;
use App\Support\RadarMindIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Movimentações de estoque.
 * - A matriz VÊ as movimentações de todas as unidades; a subempresa só as dela.
 * - Cada um só registra movimentações nos itens da PRÓPRIA unidade.
 */
class MovementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = StockMovement::with(['item', 'unit'])->visivelPara($user)->latest('movement_date')->latest('id');

        if ($user->ehMatriz() && $request->filled('unit_id')) {
            $query->where('unit_id', $request->integer('unit_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('movement_date', '>=', $request->date('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('movement_date', '<=', $request->date('end_date'));
        }

        // A matriz vê as próprias movimentações separadas das movimentações das subempresas
        $movimentacoesDasSubempresas = $user->ehMatriz()
            ? (clone $query)->where('unit_id', '!=', $user->unit_id)->paginate(20, ['*'], 'pagina_subempresas')->withQueryString()
            : null;
        if ($user->ehMatriz()) {
            $query->where('unit_id', $user->unit_id);
        }

        return view('radarmind.movements.index', [
            'movements' => $query->paginate(20)->withQueryString(),
            'movimentacoesDasSubempresas' => $movimentacoesDasSubempresas,
            // Só os itens da própria unidade podem ser movimentados
            'items' => Item::where('unit_id', $user->unit_id)->where('status', '!=', 'Inativo')->orderBy('name')->get(),
            'ehMatriz' => $user->ehMatriz(),
            'minhaUnidade' => $user->unidade,
            'units' => $user->ehMatriz() ? Unit::orderBy('id')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $unidade = $request->user()->unidadeObrigatoria();

        $data = $request->validate([
            'type' => ['required', Rule::in(['Entrada', 'Saída'])],
            'item_id' => ['required', Rule::exists('radarmind_items', 'id')->where('unit_id', $unidade->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'], 'movement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'item_id.exists' => 'Escolha um item do estoque da sua unidade.',
        ]);

        DB::transaction(function () use ($data) {
            $item = Item::lockForUpdate()->findOrFail($data['item_id']);
            if ($data['type'] === 'Saída' && $item->quantity < $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'A quantidade de saída é maior que o estoque disponível.']);
            }
            $item->quantity += $data['type'] === 'Entrada' ? $data['quantity'] : -$data['quantity'];
            $item->save();
            StockMovement::create($data + ['unit_id' => $item->unit_id, 'user_id' => RadarMindIntegration::userId()]);
        });

        return back()->with('success', 'Movimentação registrada e estoque atualizado.');
    }
}
