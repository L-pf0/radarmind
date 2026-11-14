<?php

namespace App\Http\Controllers\RadarMind;

use App\Http\Controllers\Controller;
use App\Models\RadarMind\Item;
use App\Models\RadarMind\ServiceOrder;
use App\Models\RadarMind\Unit;
use App\Support\RadarMindIntegration;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceOrderController extends Controller
{
    public function index(Request $request)
    {
        $q = ServiceOrder::with(['item', 'unit'])->latest('opened_at');
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('unit_id')) {
            $q->where('unit_id', $request->integer('unit_id'));
        }

        return view('radarmind.service_orders.index', [
            'orders' => $q->paginate(20)->withQueryString(), 'items' => Item::with('unit')->where('category', 'maq')->orderBy('name')->get(),
            'units' => Unit::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:radarmind_items,id'], 'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1500'],
            'priority' => ['required', Rule::in(['Baixa', 'Média', 'Alta', 'Urgente'])], 'opened_at' => ['required', 'date'],
        ]);
        $item = Item::findOrFail($data['item_id']);
        ServiceOrder::create($data + ['unit_id' => $item->unit_id, 'status' => 'Aberta', 'user_id' => RadarMindIntegration::userId()]);

        return back()->with('success', 'Ordem de serviço aberta.');
    }

    public function updateStatus(Request $request, ServiceOrder $serviceOrder)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['Aberta', 'Em andamento', 'Concluída', 'Cancelada'])]]);
        $data['closed_at'] = $data['status'] === 'Concluída' ? now()->toDateString() : null;
        $serviceOrder->update($data);

        return back()->with('success', 'Status da O.S. atualizado.');
    }

    public function destroy(ServiceOrder $serviceOrder)
    {
        $serviceOrder->delete();

        return back()->with('success', 'Ordem de serviço excluída.');
    }
}
