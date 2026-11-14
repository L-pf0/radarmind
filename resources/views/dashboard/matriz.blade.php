{{-- resources/views/dashboard/matriz.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard Matriz - RadarMind')

@php
    $activeRoute = 'matriz';

    // ===== Valores padrão: se o controller não enviar algo, a tela abre vazia, sem erro =====
    $stats = $stats ?? [];
    $alertas = collect($alertas ?? []);
    $movimentacoes = collect($movimentacoes ?? []);
    $pedidos = collect($pedidos ?? []);

    // Ranking ordenado da maior para a menor pontuação (0 a 100): movimentações + itens de alto giro em estoque
    // Formato de cada item: ['nome' => 'Unidade A', 'pontuacao' => 98, 'movimentacoes' => 12, 'itens_alto_giro' => 4, 'cor' => 'blue']
    $ranking = collect($ranking ?? [])
        ->sortByDesc('pontuacao')
        ->values();

    // Cards de indicadores rápidos (a variação é opcional)
    $cards = [
        [
            'rotulo' => 'Total de Itens em Estoque',
            'valor' => $stats['itens_estoque'] ?? null,
            'icone' => 'bi-box-seam',
            'cor' => 'blue',
            'variacao' => $stats['itens_estoque_var'] ?? null,
        ],
        [
            'rotulo' => 'Movimentações',
            'valor' => $stats['movimentacoes'] ?? null,
            'icone' => 'bi-truck',
            'cor' => 'green',
            'variacao' => $stats['movimentacoes_var'] ?? null,
        ],
    ];

    // Traduz o status do pedido para classe CSS e texto
    $statusPedido = [
        'pending' => ['classe' => 'status-pending', 'texto' => 'Aguardando aprovação'],
        'review' => ['classe' => 'status-review', 'texto' => 'Em análise'],
        'sent' => ['classe' => 'status-sent', 'texto' => 'Enviado'],
    ];

    // Ícone de cada tipo de alerta
    $iconeAlerta = [
        'danger' => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'success' => 'bi-tools',
    ];
@endphp

{{-- O card "Logado em..." fica no cabeçalho do dashboard (ao lado de "Emitir relatórios"), não no topo do layout --}}
@section('sessao-no-conteudo', true)

@section('content')
    {{-- rm-painel-matriz: letras maiores no dashboard (tema-slide.css) --}}
    <div class="rm-painel-matriz rm-dashboard container-fluid px-3 px-lg-4 py-4">

        {{-- ===== Cabeçalho: onde o usuário está (esquerda) + relatórios (direita) ===== --}}
        <div class="rm-page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <x-sessao-atual />

            <a href="{{ route('radarmind.reports.index') }}" class="btn btn-rm-submit rm-header-btn">
                <i class="bi bi-file-earmark-text"></i> Emitir relatórios
            </a>
        </div>

        {{-- ===== Cards de indicadores rápidos ===== --}}
        {{-- Celular: um embaixo do outro | a partir de 576px: três lado a lado --}}
        <div class="row g-3 mb-4">
            @foreach ($cards as $card)
                <div class="col-12 col-sm-6">
                    <div class="rm-stat-card">
                        <div class="rm-stat-icon bg-icon-{{ $card['cor'] }}"><i class="bi {{ $card['icone'] }}"></i></div>
                        <div class="rm-stat-label">{{ $card['rotulo'] }}</div>
                        {{-- Se não houver valor, mostra um traço --}}
                        <div class="rm-stat-value">
                            {{ is_null($card['valor']) ? '—' : number_format($card['valor'], 0, ',', '.') }}
                        </div>
                        @if (!is_null($card['variacao']))
                            <div class="rm-stat-trend {{ $card['variacao'] >= 0 ? 'up' : 'neutral' }}">
                                <i
                                    class="bi {{ $card['variacao'] >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short' }}"></i>
                                {{ abs($card['variacao']) }}% vs. período anterior
                            </div>
                        @else
                            <div class="rm-stat-trend neutral">no período</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ===== Gráfico + Ranking + Alertas ===== --}}
        {{-- Até 767px: empilhados | 768 a 1199px: gráfico em cima, ranking e alertas lado a lado | 1200px+: 3 colunas --}}
        <div class="row g-3 mb-4 align-items-stretch">

            {{-- Gráfico de desempenho (usa os mesmos dados do ranking) --}}
            <div class="col-12 col-xl-5">
                <div class="rm-panel h-100">
                    <div class="rm-panel-header">
                        <i class="bi bi-bar-chart-fill rm-panel-icon icon-blue"></i>
                        <h2 class="rm-panel-title">Desempenho Geral das Subempresas</h2>
                    </div>

                    @if ($ranking->isNotEmpty())
                        <div class="rm-chart-wrapper">
                            {{-- O JS lê os dados destes atributos --}}
                            <canvas id="desempenhoChart" role="img" aria-label="Gráfico de desempenho das subempresas"
                                data-labels='@json($ranking->pluck('nome'))'
                                data-values='@json($ranking->pluck('pontuacao'))'></canvas>
                        </div>
                    @else
                        <div class="rm-empty">Sem dados de desempenho.</div>
                    @endif
                </div>
            </div>

            {{-- Ranking de desempenho das subempresas --}}
            <div class="col-12 col-md-6 col-xl-4">
                <div class="rm-panel h-100">
                    <div class="rm-panel-header">
                        <i class="bi bi-trophy-fill rm-panel-icon icon-blue"></i>
                        <h2 class="rm-panel-title">Ranking de Desempenho</h2>
                    </div>
                    <p class="rm-rank-meta mb-2">Pontuação = movimentações registradas (50%) + itens de alto giro em estoque (50%).</p>

                    <ol class="rm-ranking">
                        @forelse ($ranking as $i => $sub)
                            @php
                                $pontos = (float) $sub['pontuacao'];
                                // Cor da barra conforme a faixa de pontuação
                                $faixa = $pontos >= 80 ? 'good' : ($pontos >= 50 ? 'mid' : 'low');
                            @endphp
                            <li class="rm-rank-item">
                                {{-- Posição: as 3 primeiras ganham destaque --}}
                                <span class="rm-rank-pos {{ $i < 3 ? 'top-' . ($i + 1) : '' }}">{{ $i + 1 }}º</span>

                                <div class="rm-rank-body">
                                    <div class="rm-rank-head">
                                        <span class="rm-rank-name">{{ $sub['nome'] }}</span>
                                        <span class="rm-rank-score">{{ number_format($pontos, 0) }}%</span>
                                    </div>
                                    <div class="rm-rank-bar">
                                        <div class="rm-rank-fill {{ $faixa }}"
                                            style="width: {{ min(max($pontos, 0), 100) }}%"></div>
                                    </div>
                                    @isset($sub['movimentacoes'])
                                        <div class="rm-rank-meta">
                                            {{ number_format($sub['movimentacoes'], 0, ',', '.') }} {{ $sub['movimentacoes'] === 1 ? 'movimentação' : 'movimentações' }}
                                            · {{ $sub['itens_alto_giro'] }} {{ $sub['itens_alto_giro'] === 1 ? 'item' : 'itens' }} de alto giro
                                        </div>
                                    @endisset
                                </div>
                            </li>
                        @empty
                            <li class="rm-empty">Nenhuma subempresa no ranking.</li>
                        @endforelse
                    </ol>

                    <a href="{{ route('radarmind.reports.index') }}" class="rm-panel-footer-link">Ver todas as subempresas <i
                            class="bi bi-arrow-right"></i></a>
                </div>
            </div>

            {{-- Alertas críticos --}}
            <div class="col-12 col-md-6 col-xl-3">
                <div class="rm-panel h-100">
                    <div class="rm-panel-header">
                        <i class="bi bi-bell-fill rm-panel-icon icon-blue"></i>
                        {{-- Alertas da Central de Notificações: da matriz e de todas as subempresas --}}
                        <h2 class="rm-panel-title">Alertas Recentes</h2>
                        @if (($alertasNaoLidos ?? 0) > 0)
                            <span class="rm-badge-count" title="Alertas não lidos">{{ $alertasNaoLidos }}</span>
                        @endif
                        <a href="{{ route('alertas.index') }}" class="rm-panel-header-link">Ver todos <i
                                class="bi bi-arrow-right"></i></a>
                    </div>

                    {{-- Cada alerta: ['tipo' => 'danger|warning|success', 'unidade', 'descricao', 'categoria', 'tempo'] --}}
                    @forelse ($alertas as $alerta)
                        <div class="rm-alert-item">
                            <div class="rm-alert-icon icon-{{ $alerta['tipo'] }}">
                                <i class="bi {{ $iconeAlerta[$alerta['tipo']] ?? 'bi-bell' }}"></i>
                            </div>
                            <div class="rm-alert-body">
                                <div class="rm-alert-title">{{ $alerta['unidade'] }}
                                    <span class="rm-alert-desc">{{ $alerta['descricao'] }}</span>
                                </div>
                                <div class="rm-alert-meta">{{ $alerta['categoria'] }}
                                    <span class="rm-alert-time">{{ $alerta['tempo'] }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rm-empty">Nenhum alerta no momento.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ===== Movimentações + Pedidos + O.S ===== --}}
        {{-- Até 767px: empilhados | 768 a 1199px: movimentações em cima, pedidos e O.S lado a lado | 1200px+: 3 colunas --}}
        <div class="row g-3 align-items-stretch">

            {{-- Últimas movimentações --}}
            <div class="col-12 col-xl-5">
                <div class="rm-panel h-100">
                    <div class="rm-panel-header">
                        <i class="bi bi-arrow-left-right rm-panel-icon icon-blue"></i>
                        <h2 class="rm-panel-title">Últimas Movimentações</h2>
                        <a href="{{ route('radarmind.movements.index') }}"
                            class="rm-panel-header-link">Ver todas <i class="bi bi-arrow-right"></i></a>
                    </div>

                    <div class="table-responsive">
                        {{-- rm-table-stack: no celular, cada linha vira um bloquinho (usa os data-label das células) --}}
                        <table class="table rm-table rm-table-stack align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Subempresa</th>
                                    <th>Item</th>
                                    <th>Tipo</th>
                                    <th>Quantidade</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Cada item: ['data', 'subempresa', 'cor', 'item', 'tipo' => 'entrada|saida', 'quantidade'] --}}
                                @forelse ($movimentacoes as $mov)
                                    @php $entrada = $mov['tipo'] === 'entrada'; @endphp
                                    <tr>
                                        <td data-label="Data">
                                            {{ \Carbon\Carbon::parse($mov['data'])->format('d/m/Y H:i') }}</td>
                                        <td data-label="Subempresa"><span><span
                                                    class="rm-dot dot-{{ $mov['cor'] ?? 'gray' }}"></span>
                                                {{ $mov['subempresa'] }}</span></td>
                                        <td data-label="Item">{{ $mov['item'] }}</td>
                                        <td data-label="Tipo"><span
                                                class="rm-tag {{ $entrada ? 'tag-in' : 'tag-out' }}">{{ $entrada ? 'Entrada' : 'Saída' }}</span>
                                        </td>
                                        <td data-label="Quantidade"
                                            class="{{ $entrada ? 'text-success' : 'text-danger' }} fw-semibold">
                                            {{ $entrada ? '+' : '-' }}{{ abs($mov['quantidade']) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="rm-empty">Nenhuma movimentação registrada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Pedidos de reposição em aberto --}}
            <div class="col-12 col-md-7 col-xl-4">
                <div class="rm-panel h-100">
                    <div class="rm-panel-header">
                        <i class="bi bi-cart3 rm-panel-icon icon-blue"></i>
                        <h2 class="rm-panel-title">Pedidos de Reposição em Aberto</h2>
                        <a href="{{ route('pedidos-reposicao.index') }}" class="rm-panel-header-link">Ver todos <i class="bi bi-arrow-right"></i></a>
                    </div>

                    <div class="table-responsive">
                        <table class="table rm-table rm-table-stack align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Subempresa</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Cada item: ['subempresa', 'cor', 'data', 'status' => 'pending|review|sent', 'status_texto' (opcional)] --}}
                                @forelse ($pedidos as $pedido)
                                    @php $st = $statusPedido[$pedido['status']] ?? $statusPedido['pending']; @endphp
                                    <tr>
                                        <td data-label="Subempresa"><span><span
                                                    class="rm-dot dot-{{ $pedido['cor'] ?? 'gray' }}"></span>
                                                {{ $pedido['subempresa'] }}</span></td>
                                        <td data-label="Data">{{ \Carbon\Carbon::parse($pedido['data'])->format('d/m/Y') }}
                                        </td>
                                        <td data-label="Status"><span
                                                class="rm-status {{ $st['classe'] }}">{{ $pedido['status_texto'] ?? $st['texto'] }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="rm-empty">Nenhum pedido em aberto.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Card Ordens de Serviço --}}
            <div class="col-12 col-md-5 col-xl-3">
                <div class="rm-os-panel h-100">
                    <div class="rm-os-icon"><i class="bi bi-check-circle-fill"></i></div>
                    <h2 class="rm-os-title">Ordens de Serviço</h2>
                    <p class="rm-os-desc">Acompanhe e gerencie as O.S das suas subempresas.</p>

                    <div class="rm-os-shield">
                        <i class="bi bi-shield-check"></i>
                    </div>

                    <a href="{{ route('os.create') }}" class="btn btn-rm-submit w-100">
                        <i class="bi bi-tools"></i> Registrar O.S <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="{{ route('os.index') }}" class="btn btn-rm-submit w-100">
                        <i class="bi bi-tools"></i> Verificar O.S <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    {{-- defer: os scripts carregam sem travar a página e continuam rodando nesta ordem --}}
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script defer src="@assetVersionado('js/dashboardMatriz.js')"></script>
@endpush
