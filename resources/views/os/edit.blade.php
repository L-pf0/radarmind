{{-- resources/views/os/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Editar status da O.S - RadarMind')

@php
    $activeRoute = 'os';

    $prioridades = [
        'Baixa' => ['classe' => 'p-baixa', 'icone' => 'bi-arrow-down-circle-fill'],
        'Média' => ['classe' => 'p-media', 'icone' => 'bi-dash-circle-fill'],
        'Alta' => ['classe' => 'p-alta', 'icone' => 'bi-arrow-up-circle-fill'],
        'Urgente' => ['classe' => 'p-urgente', 'icone' => 'bi-exclamation-triangle-fill'],
    ];
    $prio = $prioridades[$os->prioridade] ?? ['classe' => 'p-baixa', 'icone' => 'bi-circle-fill'];

    // Os quatro status possíveis: classe de cor, ícone e uma frase curta
    $statusOpcoes = [
        'Aguardando autorização' => [
            'classe' => 's-pending',
            'icone' => 'bi-hourglass-split',
            'texto' => 'Ainda será analisada',
        ],
        'Negada' => ['classe' => 's-denied', 'icone' => 'bi-x-circle-fill', 'texto' => 'Pedido recusado'],
        'Autorizada' => ['classe' => 's-approved', 'icone' => 'bi-check-circle-fill', 'texto' => 'Pedido aprovado'],
        // Final: vai para "Finalizadas" e avisa a unidade que registrou
        'Feita' => ['classe' => 's-done', 'icone' => 'bi-patch-check-fill', 'texto' => 'Serviço concluído'],
    ];

    $statusAtual = old('status', $os->status);
@endphp

@section('content')
    <div class="rm-dashboard container-fluid px-3 px-lg-4 py-4">
        <div class="rm-form-wrap">

            <a href="{{ route('os.index') }}" class="rm-panel-header-link">
                <i class="bi bi-arrow-left"></i> Voltar para as O.S
            </a>

            {{-- ===== Detalhes da O.S (somente leitura) ===== --}}
            <section class="rm-panel rm-form-panel mt-3">
                <div class="rm-form-head">
                    <span class="rm-form-head-icon"><i class="bi bi-clipboard2-check"></i></span>
                    <h1 class="rm-page-title mb-1">Editar status da O.S</h1>
                    <p class="rm-page-subtitle mb-0">Confira os dados do pedido e escolha o novo status.</p>
                </div>

                <div class="rm-os-details">
                    <div class="rm-os-detail">
                        <span class="rm-os-label">Máquina/peça</span>
                        <span class="rm-os-value rm-os-wrap">{{ $os->maquina }}</span>
                    </div>
                    <div class="rm-os-detail">
                        <span class="rm-os-label">Tipo do problema</span>
                        <span class="rm-os-value rm-os-wrap">{{ $os->tipo_problema }}</span>
                    </div>
                    <div class="rm-os-detail">
                        <span class="rm-os-label">Solicitante</span>
                        <span class="rm-os-value rm-os-wrap"><i class="bi bi-person"></i> {{ $os->solicitante }}</span>
                    </div>
                    <div class="rm-os-detail">
                        <span class="rm-os-label">Registrada em</span>
                        <span class="rm-os-value">{{ $os->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="rm-os-detail">
                        <span class="rm-os-label">Prioridade</span>
                        <span class="rm-os-prio-tag rm-os-card {{ $prio['classe'] }}">
                            <i class="bi {{ $prio['icone'] }}"></i> {{ $os->prioridade }}
                        </span>
                    </div>
                    <div class="rm-os-detail rm-os-detail-full">
                        <span class="rm-os-label">Descrição do defeito</span>
                        <p class="rm-os-desc-text mb-0">{{ $os->descricao }}</p>
                    </div>
                </div>
            </section>

            {{-- ===== Escolha do status ===== --}}
            <form id="formStatus" class="rm-panel rm-form-panel mt-4" method="POST" action="{{ route('os.status', $os) }}"
                data-original="{{ $os->status }}" novalidate>
                @csrf
                @method('PUT')

                <h2 class="rm-panel-title mb-3">Novo status</h2>

                <div class="rm-status-grid @error('status') is-invalid @enderror" role="radiogroup"
                    aria-label="Status da O.S">
                    @foreach ($statusOpcoes as $nome => $info)
                        <input type="radio" name="status" id="st-{{ $info['classe'] }}" value="{{ $nome }}"
                            @checked($statusAtual === $nome)>
                        <label for="st-{{ $info['classe'] }}" class="{{ $info['classe'] }}">
                            <i class="bi {{ $info['icone'] }}"></i>
                            <span class="rm-status-opt-text">
                                {{ $nome }}
                                <small>{{ $info['texto'] }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
                <span class="rm-field-error">{{ $errors->first('status') }}</span>

                <p class="rm-os-feita-aviso">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Ao salvar como <strong>Feita</strong>, a O.S vai para <strong>Finalizadas</strong>, a unidade
                        que a registrou recebe um alerta e o status não pode mais ser alterado.</span>
                </p>

                <div class="rm-form-actions">
                    <a href="{{ route('os.index') }}" class="btn rm-btn-back">Cancelar</a>
                    <button type="submit" id="btnSalvarStatus" class="btn btn-rm-submit" disabled>
                        <i class="bi bi-check2-circle me-1"></i> Salvar status
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script defer src="@assetVersionado('js/os.js')"></script>
@endpush
