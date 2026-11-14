{{-- resources/views/dashboard/subempresa.blade.php --}}
{{-- Dashboard da Subempresa (feito pelo grupo como front-end + API separados).
     Mostra o resumo da unidade do usuário logado (rotas JSON do SubempresaController).
     Os botões levam para as páginas do sistema: Estoque, Movimentações, O.S. e Pedidos de reposição. --}}
@extends('layouts.app')

@section('title', 'Dashboard da Subempresa - RadarMind')

@push('styles')
    <link rel="stylesheet" href="@assetVersionado('css/dashboardSubempresa.css')">
@endpush

{{-- O card "Logado em..." fica no cabeçalho do dashboard, não no topo do layout --}}
@section('sessao-no-conteudo', true)

@section('content')
    {{-- Mesmo contêiner do dashboard da matriz: ocupa a largura toda da página --}}
    <div class="rm-sub rm-dashboard container-fluid px-3 px-lg-4 py-4" id="subempresaApp"
        data-api="{{ url('subempresa/api') }}">

        {{-- ===== Cabeçalho ===== --}}
        <header class="rm-sub-header">
            <div>
                <x-sessao-atual class="mb-3" />
                <h1 class="rm-page-title mb-1">{{ $unidade->name }}</h1>
            </div>
        </header>

        <section class="kpis" id="kpis" aria-live="polite"></section>

        {{-- ===== Atalhos: levam para as páginas do sistema ===== --}}
        <section class="acoes" aria-label="Ações rápidas">
            <a class="btn" href="{{ route('radarmind.inventory.index') }}#form-item"><i class="bi bi-box-seam"></i>
                Cadastrar estoque</a>
            <a class="btn" href="{{ route('radarmind.movements.index') }}#registrar"><i
                    class="bi bi-arrow-left-right"></i>
                Cadastrar movimentação</a>
            <a class="btn" href="{{ route('os.create') }}"><i class="bi bi-tools"></i> Registrar O.S.</a>
            <a class="btn" href="{{ route('pedidos-reposicao.index') }}"><i class="bi bi-cart3"></i> Registrar pedido de
                reposição</a>
        </section>

        <section class="grade">
            <article class="card">
                {{-- Alertas da Central de Notificações: só os da própria unidade --}}
                <h2><i class="bi bi-bell"></i> Alertas da unidade
                    <span class="tag atencao" id="alertasNaoLidos"></span>
                    <a class="rm-sub-ver" href="{{ route('alertas.index') }}">Ver todos os alertas <i
                            class="bi bi-arrow-right"></i></a>
                </h2>
                <ul id="alertas" class="lista"></ul>
            </article>
            <article class="card">
                <h2><i class="bi bi-bar-chart"></i> Desempenho da unidade</h2>
                <div id="desempenho"></div>
            </article>
        </section>

        <section class="card">
            <h2><i class="bi bi-arrow-left-right"></i> Movimentações recentes
                <a class="rm-sub-ver" href="{{ route('radarmind.movements.index') }}">Ver todas <i
                        class="bi bi-arrow-right"></i></a>
            </h2>
            <div class="rolagem">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Item</th>
                            <th>Tipo</th>
                            <th>Qtd.</th>
                        </tr>
                    </thead>
                    <tbody id="tbMov"></tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2><i class="bi bi-box-seam"></i> Estoque da unidade
                <a class="rm-sub-ver" href="{{ route('radarmind.inventory.index') }}">Ver estoque completo <i
                        class="bi bi-arrow-right"></i></a>
            </h2>
            <div class="rolagem">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Item</th>
                            <th>Categoria</th>
                            <th>Saldo</th>
                            <th>Mínimo</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="tbEstoque"></tbody>
                </table>
            </div>
        </section>

        <section class="grade">
            <article class="card">
                <h2><i class="bi bi-tools"></i> Ordens de serviço da unidade
                    <a class="rm-sub-ver" href="{{ route('os.index') }}">Ver todas <i class="bi bi-arrow-right"></i></a>
                </h2>
                <ul id="listaOs" class="lista"></ul>
            </article>
            <article class="card">
                <h2><i class="bi bi-cart3"></i> Pedidos de reposição
                    <a class="rm-sub-ver" href="{{ route('pedidos-reposicao.index') }}">Ver todos <i
                            class="bi bi-arrow-right"></i></a>
                </h2>
                <ul id="listaRep" class="lista"></ul>
            </article>
        </section>

        <div id="toast" role="status"></div>
    </div>
@endsection

@push('scripts')
    <script defer src="@assetVersionado('js/dashboardSubempresa.js')"></script>
@endpush
