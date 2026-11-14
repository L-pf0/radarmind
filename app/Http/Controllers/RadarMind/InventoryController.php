<?php

namespace App\Http\Controllers\RadarMind;

use App\Http\Controllers\Controller;
use App\Models\PedidoReposicao;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Estoque de matriz e subempresas.
 * - A matriz VÊ o estoque de todas as unidades (com filtro por unidade).
 * - A subempresa vê só o da própria unidade.
 * - Cada um só cadastra, edita e exclui itens da PRÓPRIA unidade.
 */
class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Item::with('unit')->visivelPara($user);

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")->orWhere('serial_number', 'like', "%{$q}%"));
        }
        if ($user->ehMatriz() && $request->filled('unit_id')) {
            $query->where('unit_id', $request->integer('unit_id'));
        }
        if ($request->filled('turnover')) {
            $query->where('turnover', $request->string('turnover'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        $items = $query->orderBy('category')->orderBy('name')->get();
        $visiveis = Item::visivelPara($user)->get();

        // A matriz vê o próprio estoque separado do estoque das subempresas
        [$itensDaMinhaUnidade, $itensDasSubempresas] = $items->partition(fn (Item $item) => $item->pertenceAUnidadeDe($user));

        return view('radarmind.inventory.index', [
            'items' => $items,
            'itensDaMinhaUnidade' => $itensDaMinhaUnidade,
            'itensDasSubempresas' => $itensDasSubempresas,
            'ehMatriz' => $user->ehMatriz(),
            'minhaUnidade' => $user->unidade,
            'units' => $user->ehMatriz() ? Unit::orderBy('id')->get() : collect(),
            'escopo' => $user->ehMatriz() ? 'todas as unidades' : $user->unidade?->name,
            'totalQuantity' => $visiveis->sum('quantity'),
            'maintenanceCount' => $visiveis->where('status', 'Em manutenção')->count(),
            'alertsCount' => $visiveis->filter(fn ($i) => $i->alert_level !== 'Normal')->count(),
            'replenishments' => PedidoReposicao::with('unit')->visivelPara($user)
                ->where('status', 'Em aberto')->latest('data_pedido')->latest('id')->take(8)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateItem($request);

        // O item sempre entra na unidade de quem cadastra (a matriz não cadastra para subempresas)
        Item::create($data + ['unit_id' => $request->user()->unidadeObrigatoria()->id]);

        return back()->with('success', 'Item cadastrado com sucesso.');
    }

    public function edit(Request $request, Item $item)
    {
        $this->garantirQueEhDaMinhaUnidade($request, $item);

        return view('radarmind.inventory.edit', ['item' => $item]);
    }

    public function update(Request $request, Item $item)
    {
        $this->garantirQueEhDaMinhaUnidade($request, $item);

        $item->update($this->validateItem($request, $item));

        return redirect()->route('radarmind.inventory.index')->with('success', 'Item atualizado com sucesso.');
    }

    public function destroy(Request $request, Item $item)
    {
        $this->garantirQueEhDaMinhaUnidade($request, $item);

        if ($item->movements()->exists() || $item->pedidosReposicao()->exists() || $item->serviceOrders()->exists()) {
            return back()->with('error', 'Este item possui histórico e não pode ser excluído. Marque-o como Inativo.');
        }
        $item->delete();

        return back()->with('success', 'Item excluído.');
    }

    /**
     * A matriz vê o estoque das subempresas, mas não mexe nele (e vice-versa).
     */
    private function garantirQueEhDaMinhaUnidade(Request $request, Item $item): void
    {
        abort_unless($item->pertenceAUnidadeDe($request->user()), 403, 'Você só pode alterar o estoque da sua unidade.');
    }

    /**
     * Obrigatórios (peça e maquinário): categoria, código, nome, localização, quantidade, mínimo,
     * lote, número de série, status e giro. Tipo, modelo, ano e horímetro continuam opcionais.
     *
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, ?Item $item = null): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(['peca', 'maq'])],
            'sku' => ['required', 'string', 'max:50', Rule::unique('radarmind_items', 'sku')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:160'],
            'machine_type' => ['nullable', 'string', 'max:100'], 'model_application' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)], 'hour_meter' => ['nullable', 'numeric', 'min:0'],
            'location' => ['required', 'string', 'max:160'], 'quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'minimum_quantity' => ['required', 'integer', 'min:0', 'max:999999'], 'lot' => ['required', 'string', 'max:100'],
            'serial_number' => ['required', 'string', 'max:120'], 'status' => ['required', Rule::in(['Ativo', 'Em manutenção', 'Inativo'])],
            'turnover' => ['required', Rule::in(['Alto giro', 'Médio giro', 'Baixo giro'])],
        ], [
            'required' => 'Preencha o campo :attribute.',
            'in' => 'Escolha uma opção válida em :attribute.',
            'sku.unique' => 'Já existe um item com este código/SKU.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'numeric' => 'O campo :attribute deve ser um número.',
            'min' => 'O campo :attribute não pode ser menor que :min.',
            'max' => 'O campo :attribute passou do limite permitido.',
        ], [
            'category' => 'Categoria', 'sku' => 'Código/SKU', 'name' => 'Nome', 'location' => 'Localização física',
            'quantity' => 'Quantidade disponível', 'minimum_quantity' => 'Quantidade mínima', 'lot' => 'Lote',
            'serial_number' => 'Número de série', 'status' => 'Status', 'turnover' => 'Classificação de giro',
            'machine_type' => 'Tipo', 'model_application' => 'Modelo/aplicação', 'year' => 'Ano', 'hour_meter' => 'Horímetro',
        ]);
    }
}
