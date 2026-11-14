@extends(config('radarmind.layout'))
@section('title','Maquinário e estoque · RadarMind')
@section('content')
{{-- rm-estoque: letras maiores nesta tela (tema-slide.css). Ícones: trator = maquinário, chave = peça avulsa --}}
<div class="rm-estoque">
<h1>Maquinário e estoque</h1>
<section class="grid g3"><div class="card stat"><div class="tile blue"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></div><div class="lbl">Total de unidades em estoque</div><strong>{{ number_format($totalQuantity,0,',','.') }}</strong><div class="hint">{{ $escopo }}</div></div><div class="card stat"><div class="tile green"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></div><div class="lbl">Itens em manutenção</div><strong>{{ $maintenanceCount }}</strong><div class="hint">{{ $escopo }}</div></div><div class="card stat"><div class="tile yellow"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div><div class="lbl">Alertas de estoque baixo ou crítico</div><strong>{{ $alertsCount }}</strong><div class="hint">{{ $escopo }}</div></div></section>
<section class="card mt"><div class="card-title"><i class="fa-solid fa-warehouse" aria-hidden="true"></i> {{ $ehMatriz ? 'Estoque da Matriz' : 'Estoque' }} <span class="push" style="display:flex;gap:8px"><button class="pill" type="button" data-toggle="form-item">Cadastrar item</button><a class="pill" href="{{ route('pedidos-reposicao.index') }}">Solicitar reposição</a></span></div>
{{-- Fica aberto quando o cadastro volta com erro, para a pessoa corrigir --}}
<form id="form-item" @class(['form-block', 'hidden' => ! $errors->any()]) method="POST" action="{{ route('radarmind.inventory.store') }}">@csrf
@include('radarmind.inventory.partials.campos-item', ['item' => null])
<div class="actions"><button class="btn" type="submit">Salvar item</button></div></form>
<form class="filters" method="GET"><input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nome, código ou série">@if($ehMatriz)<select name="unit_id"><option value="">Todas as unidades</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(request('unit_id')==$unit->id)>{{ $unit->name }}</option>@endforeach</select>@endif<select name="turnover"><option value="">Todos os giros</option>@foreach(['Alto giro','Médio giro','Baixo giro'] as $turn)<option @selected(request('turnover')===$turn)>{{ $turn }}</option>@endforeach</select><button class="btn" type="submit">Filtrar</button><a class="btn ghost" href="{{ route('radarmind.inventory.index') }}">Limpar</a></form>
<div class="chips"><a class="chip {{ !request('category')?'on':'' }}" href="{{ route('radarmind.inventory.index',request()->except('category')) }}">Todos</a><a class="chip {{ request('category')==='peca'?'on':'' }}" href="{{ route('radarmind.inventory.index',array_merge(request()->except('category'),['category'=>'peca'])) }}"><i class="fa-solid fa-wrench" aria-hidden="true"></i> Peças avulsas</a><a class="chip {{ request('category')==='maq'?'on':'' }}" href="{{ route('radarmind.inventory.index',array_merge(request()->except('category'),['category'=>'maq'])) }}"><i class="fa-solid fa-tractor" aria-hidden="true"></i> Maquinário inteiro</a></div>
@include('radarmind.inventory.partials.tabela-itens', ['itens' => $ehMatriz ? $itensDaMinhaUnidade : $items, 'mensagemVazia' => 'Nenhum item encontrado com os filtros atuais.'])</section>
@if($ehMatriz)<section class="card mt" id="estoque-subempresas"><div class="card-title"><i class="fa-solid fa-building" aria-hidden="true"></i> Estoque das Subempresas <span class="push cell-sub">Somente consulta</span></div>
@include('radarmind.inventory.partials.tabela-itens', ['itens' => $itensDasSubempresas, 'mensagemVazia' => 'Nenhum item das subempresas encontrado com os filtros atuais.'])</section>@endif
@php
    // Mesmos pedidos da tabela abaixo, em JSON, para o filtro por unidade feito em Vue (js/filtro-reposicao.js)
    $pedidosParaFiltro = $replenishments->map(fn ($r) => [
        'id' => $r->id,
        'unidade' => $r->unit?->name ?? $r->unidade,
        'item' => $r->item,
        'quantidade' => $r->quantidade,
        'data' => $r->data_pedido?->format('d/m/Y'),
        'status' => $r->status,
        'url_confirmar' => $r->podeConfirmarChegada(auth()->user()) ? route('pedidos-reposicao.index', ['pedido' => $r->id]) : null,
    ])->values();
@endphp
<section class="card mt" id="pedidos-reposicao"><div class="card-title"><i class="fa-solid fa-cart-flatbed" aria-hidden="true"></i> Pedidos de reposição em aberto <a class="link push" href="{{ route('pedidos-reposicao.index') }}">Ver todos os pedidos <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
{{-- O Vue monta aqui e troca a tabela abaixo pela versão com filtro. Se o Vue não carregar, a tabela do Blade continua funcionando. --}}
<div id="filtroReposicao" data-pedidos='@json($pedidosParaFiltro)'><div class="table-wrap"><table><thead><tr><th>Unidade</th><th>Item</th><th>Quantidade</th><th>Data</th><th>Status</th><th></th></tr></thead><tbody>@forelse($replenishments as $r)<tr><td>{{ $r->unit?->name ?? $r->unidade }}</td><td>{{ $r->item }}</td><td>{{ $r->quantidade }}</td><td>{{ $r->data_pedido?->format('d/m/Y') }}</td><td><span class="badge low">{{ $r->status }}</span></td><td>@if($r->podeConfirmarChegada(auth()->user()))<a class="link" href="{{ route('pedidos-reposicao.index', ['pedido' => $r->id]) }}" title="Abre a confirmação de chegada com a assinatura de recebimento">Confirmar chegada</a>@endif</td></tr>@empty<tr><td colspan="6" class="empty">Nenhum pedido em aberto.</td></tr>@endforelse</tbody></table></div></div></section>
</div>
@endsection

@push('scripts')
    {{-- Vue 3 (build global com compilador de template), carregado só nesta tela --}}
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.13/dist/vue.global.prod.js"></script>
    <script src="@assetVersionado('js/filtro-reposicao.js')"></script>
@endpush