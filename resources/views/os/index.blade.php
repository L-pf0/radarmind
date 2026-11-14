{{-- resources/views/os/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Ordens de Serviço - RadarMind')

@php
    $activeRoute = 'os';

    // Dashboard de volta (matriz ou subempresa)
    $home = route('home');
    $ehMatriz = $ehMatriz ?? false;

    $ordens = collect($ordens ?? []); // em aberto
    $finalizadas = collect($finalizadas ?? []); // status "Feita"

    // Prioridade -> classe de cor e ícone
    $prioridades = [
        'Baixa' => ['classe' => 'p-baixa', 'icone' => 'bi-arrow-down-circle-fill'],
        'Média' => ['classe' => 'p-media', 'icone' => 'bi-dash-circle-fill'],
        'Alta' => ['classe' => 'p-alta', 'icone' => 'bi-arrow-up-circle-fill'],
        'Urgente' => ['classe' => 'p-urgente', 'icone' => 'bi-exclamation-triangle-fill'],
    ];

    // Status das O.S. em aberto -> classe de cor e ícone ("Feita" fica no bloco Finalizadas)
    $statusMap = [
        'Aguardando autorização' => ['classe' => 'st-aguardando', 'icone' => 'bi-hourglass-split'],
        'Negada' => ['classe' => 'st-negada', 'icone' => 'bi-x-circle-fill'],
        'Autorizada' => ['classe' => 'st-autorizada', 'icone' => 'bi-check-circle-fill'],
    ];

    // Categoria do item: valor salvo no banco => ícone (Font Awesome) e texto
    $categorias = [
        'maquinario' => ['icone' => 'fa-tractor', 'texto' => 'Maquinário'],
        'peca' => ['icone' => 'fa-wrench', 'texto' => 'Peça'],
    ];
@endphp

@section('content')
    <div class="rm-dashboard container-fluid px-3 px-lg-4 py-4">

        {{-- ===== Voltar ao dashboard ===== --}}
        <div class="mb-3">
            <a href="{{ $home }}" class="rm-panel-header-link"><i class="bi bi-arrow-left"></i> Voltar ao
                dashboard</a>
        </div>

        {{-- ===== Cabeçalho ===== --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="rm-page-title mb-1">Ordens de Serviço</h1>
            </div>
            <a href="{{ route('os.create') }}" class="btn btn-rm-submit rm-header-btn">
                <i class="bi bi-plus-circle-fill"></i> Registrar O.S
            </a>
        </div>

        @if (session('sucesso'))
            <div class="alert alert-success rm-form-alert" role="alert">
                <i class="bi bi-check-circle-fill me-1"></i> {{ session('sucesso') }}
            </div>
        @endif

        @if ($ordens->isEmpty())
            <div class="rm-panel">
                <div class="rm-empty">
                    <i class="bi bi-clipboard-x d-block fs-2 mb-2"></i>
                    {{ $finalizadas->isEmpty() ? 'Nenhuma O.S registrada ainda.' : 'Nenhuma O.S em aberto. As concluídas estão em Finalizadas.' }}
                </div>
            </div>
        @else
            {{-- ===== Filtros por status ===== --}}
            <div class="rm-os-filters" role="group" aria-label="Filtrar por status">
                <button type="button" class="rm-filter active" data-filtro="todas">
                    Todas <span class="rm-filter-count">{{ $ordens->count() }}</span>
                </button>
                @foreach ($statusMap as $nome => $info)
                    <button type="button" class="rm-filter" data-filtro="{{ $nome }}">
                        {{ $nome }} <span
                            class="rm-filter-count">{{ $ordens->where('status', $nome)->count() }}</span>
                    </button>
                @endforeach
            </div>

            {{-- ===== Cartões horizontais ===== --}}
            <div class="rm-os-list">
                @foreach ($ordens as $os)
                    @php
                        $prio = $prioridades[$os->prioridade] ?? ['classe' => 'p-baixa', 'icone' => 'bi-circle-fill'];
                        $st = $statusMap[$os->status] ?? $statusMap['Aguardando autorização'];
                        $cat = $categorias[$os->categoria ?? ''] ?? null;
                    @endphp

                    <article class="rm-os-card {{ $prio['classe'] }}" data-status="{{ $os->status }}">

                        {{-- Lado esquerdo: status em cima, informações embaixo --}}
                        <div class="rm-os-corpo">

                            {{-- Status --}}
                            <div class="rm-os-st {{ $st['classe'] }}">
                                <span class="rm-os-st-icon"><i class="bi {{ $st['icone'] }}"></i></span>
                                {{ $os->status }}
                            </div>

                            {{-- Informações: item | categoria | solicitante e data | tipo do problema --}}
                            <div class="rm-os-info">
                                <div class="rm-os-campo">
                                    <div class="rm-os-label">Item</div>
                                    <div class="rm-os-value" title="{{ $os->maquina }}">{{ $os->maquina }}</div>
                                </div>

                                <div class="rm-os-campo">
                                    <div class="rm-os-label">Categoria</div>
                                    <div class="rm-os-value">
                                        @if ($cat)
                                            <i class="fa-solid {{ $cat['icone'] }}" aria-hidden="true"></i>
                                            {{ $cat['texto'] }}
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>

                                <div class="rm-os-campo">
                                    <div class="rm-os-label">Solicitado por</div>
                                    <div class="rm-os-value" title="{{ $os->solicitante }}">{{ $os->solicitante }}</div>
                                    <div class="rm-os-data">
                                        @if ($os->unit)
                                            {{ $os->unit->name }} ·
                                        @endif
                                        {{ $os->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </div>

                                <div class="rm-os-campo">
                                    <div class="rm-os-label">Tipo do problema</div>
                                    <div class="rm-os-value" title="{{ $os->tipo_problema }}">{{ $os->tipo_problema }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Lado direito: prioridade + botão editar --}}
                        <div class="rm-os-lado">
                            <span class="rm-os-prio-pill">
                                <i class="bi {{ $prio['icone'] }}"></i> {{ $os->prioridade }}
                            </span>
                            {{-- Só a matriz altera o status --}}
                            @if ($ehMatriz)
                                <a href="{{ route('os.edit', $os) }}" class="rm-os-editar">
                                    <i class="bi bi-pencil-square"></i> Editar
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div id="osSemResultado" class="rm-panel d-none mt-3">
                <div class="rm-empty">Nenhuma O.S com este status.</div>
            </div>
        @endif

        {{-- ===== Finalizadas: O.S. que a matriz marcou como "Feita" (não mudam mais) ===== --}}
        <section id="finalizadas" class="rm-os-finalizadas" aria-labelledby="tituloFinalizadas">
            <header class="rm-os-fin-head">
                <span class="rm-os-fin-head-icon"><i class="bi bi-patch-check-fill"></i></span>
                <h2 id="tituloFinalizadas" class="rm-os-fin-title">Finalizadas</h2>
                <span class="rm-os-fin-count">{{ $finalizadas->count() }}</span>
            </header>

            @forelse ($finalizadas as $os)
                @php($cat = $categorias[$os->categoria ?? ''] ?? null)
                <article class="rm-os-fin-item">
                    <span class="rm-os-fin-check" aria-hidden="true"><i class="bi bi-check2-all"></i></span>

                    <div class="rm-os-info">
                        <div class="rm-os-campo">
                            <div class="rm-os-label">O.S. #{{ $os->id }} · Item</div>
                            <div class="rm-os-value" title="{{ $os->maquina }}">{{ $os->maquina }}</div>
                        </div>

                        <div class="rm-os-campo">
                            <div class="rm-os-label">Categoria</div>
                            <div class="rm-os-value">
                                @if ($cat)
                                    <i class="fa-solid {{ $cat['icone'] }}" aria-hidden="true"></i> {{ $cat['texto'] }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>

                        <div class="rm-os-campo">
                            <div class="rm-os-label">Solicitado por</div>
                            <div class="rm-os-value" title="{{ $os->solicitante }}">{{ $os->solicitante }}</div>
                            <div class="rm-os-data">
                                @if ($os->unit)
                                    {{ $os->unit->name }} ·
                                @endif
                                {{ $os->created_at->format('d/m/Y H:i') }}
                            </div>
                        </div>

                        <div class="rm-os-campo">
                            <div class="rm-os-label">Tipo do problema</div>
                            <div class="rm-os-value" title="{{ $os->tipo_problema }}">{{ $os->tipo_problema }}</div>
                        </div>
                    </div>

                    <div class="rm-os-fin-quando">
                        <span class="rm-os-fin-badge"><i class="bi bi-check-circle-fill"></i> Feita</span>
                        <span class="rm-os-data">
                            Finalizada em {{ ($os->finalizada_em ?? $os->updated_at)->format('d/m/Y H:i') }}
                        </span>
                    </div>
                </article>
            @empty
                <p class="rm-os-fin-vazio">
                    <i class="bi bi-inbox"></i>
                    Nenhuma O.S finalizada ainda. Quando a matriz marcar uma O.S como "Feita", ela aparece aqui.
                </p>
            @endforelse
        </section>
    </div>
@endsection

@push('scripts')
    <script defer src="@assetVersionado('js/os.js')"></script>
@endpush
