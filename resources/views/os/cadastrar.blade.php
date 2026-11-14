{{-- resources/views/os/cadastrar.blade.php --}}
{{-- Usa o mesmo layout (e a mesma navbar) de todas as telas do sistema --}}
@extends('layouts.app')

@section('title', 'Registrar O.S | RadarMind')

@php
    // Dashboard do usuário logado: matriz vai para /matriz, subempresa para /subempresa
    $home = route('home');

    // Níveis de prioridade: 'texto' => [classe de cor, ícone]
    $niveis = [
        'Baixa' => ['p-baixa', 'bi-arrow-down-circle-fill'],
        'Média' => ['p-media', 'bi-dash-circle-fill'],
        'Alta' => ['p-alta', 'bi-arrow-up-circle-fill'],
        'Urgente' => ['p-urgente', 'bi-exclamation-triangle-fill'],
    ];
@endphp

@section('content')
    {{-- rm-os-page: as regras genéricas do CSS de O.S só valem dentro desta classe --}}
    <div class="rm-dashboard rm-os-page px-3 px-md-4 py-4">

        {{-- Voltar ao dashboard (canto esquerdo da página) --}}
        <div class="mb-3">
            <a href="{{ $home }}" class="rm-panel-header-link"><i class="bi bi-arrow-left"></i> Voltar ao
                dashboard</a>
        </div>

        <div class="rm-form-wrap">

            @if (session('sucesso'))
                <div class="alert alert-success rm-form-alert" role="alert">
                    <i class="bi bi-check-circle-fill me-1"></i> {{ session('sucesso') }}
                </div>
            @endif

            <form id="formOS" class="rm-panel rm-form-panel" method="POST" action="{{ route('os.store') }}"
                novalidate>
                @csrf

                <div class="rm-form-head">
                    <span class="rm-form-head-icon"><i class="bi bi-clipboard2-plus"></i></span>
                    <h1 class="rm-page-title mb-1">Registro de O.S</h1>
                    <p class="rm-page-subtitle mb-0">Informe a máquina ou peça com defeito. A matriz vai analisar o
                        pedido.</p>
                </div>

                {{-- Situação inicial (só informativa: o sistema define ao salvar) --}}
                <div class="rm-os-inicial">
                    <span class="rm-os-inicial-pill"><i class="bi bi-hourglass-split"></i> Aguardando autorização</span>
                    <span>Todo pedido começa com esta situação. A matriz decide o próximo passo.</span>
                </div>

                <div class="mb-3">
                    <label for="solicitante" class="form-label rm-form-label">Solicitante</label>
                    <div class="input-group rm-input-group @error('solicitante') is-invalid @enderror">
                        <span class="input-group-text ic-blue"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="form-control" id="solicitante" name="solicitante"
                            value="{{ old('solicitante') }}" placeholder="Nome de quem está solicitando" required>
                    </div>
                    <span class="rm-field-error" data-erro-for="solicitante">{{ $errors->first('solicitante') }}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="maquina" class="form-label rm-form-label">Máquina/peça</label>
                        <div class="input-group rm-input-group @error('maquina') is-invalid @enderror">
                            <span class="input-group-text ic-green"><i class="bi bi-gear-wide-connected"></i></span>
                            <input type="text" class="form-control" id="maquina" name="maquina"
                                value="{{ old('maquina', request('maquina')) }}" placeholder="Ex.: Colhedora 3200" required>
                        </div>
                        <span class="rm-field-error" data-erro-for="maquina">{{ $errors->first('maquina') }}</span>
                    </div>

                    <div class="col-md-4">
                        <label for="categoria" class="form-label rm-form-label">Categoria</label>
                        <div class="input-group rm-input-group @error('categoria') is-invalid @enderror">
                            <span class="input-group-text ic-blue"><i class="bi bi-boxes"></i></span>
                            <select class="form-select" id="categoria" name="categoria" required>
                                {{-- Vindo de um alerta de maquinário (?maquina=...), já escolhe "Maquinário" --}}
                                @php($categoriaInicial = old('categoria', request('maquina') ? 'maquinario' : null))
                                <option value="" disabled @selected(!$categoriaInicial)>Escolha</option>
                                <option value="maquinario" @selected($categoriaInicial === 'maquinario')>Maquinário</option>
                                <option value="peca" @selected($categoriaInicial === 'peca')>Peça</option>
                            </select>
                        </div>
                        <span class="rm-field-error" data-erro-for="categoria">{{ $errors->first('categoria') }}</span>
                    </div>

                    <div class="col-md-4">
                        <label for="tipo_problema" class="form-label rm-form-label">Tipo de problema</label>
                        <div class="input-group rm-input-group @error('tipo_problema') is-invalid @enderror">
                            <span class="input-group-text ic-orange"><i class="bi bi-tools"></i></span>
                            <input type="text" class="form-control" id="tipo_problema" name="tipo_problema"
                                value="{{ old('tipo_problema') }}" maxlength="255" list="tiposProblema"
                                placeholder="Digite ou escolha" autocomplete="off" required>
                        </div>
                        <datalist id="tiposProblema">
                            <option value="Mecânico">
                            <option value="Elétrico">
                            <option value="Hidráulico">
                            <option value="Estrutural">
                            <option value="Desgaste de peça">
                            <option value="Vazamento">
                        </datalist>
                        <span class="rm-field-error"
                            data-erro-for="tipo_problema">{{ $errors->first('tipo_problema') }}</span>
                    </div>
                </div>

                <div class="my-3">
                    <span class="form-label rm-form-label d-block">Nível de prioridade</span>
                    <div class="rm-priority @error('prioridade') is-invalid @enderror" id="prioridade"
                        role="radiogroup" aria-label="Nível de prioridade">
                        @foreach ($niveis as $nivel => [$classe, $icone])
                            <input type="radio" name="prioridade" id="prio-{{ $classe }}"
                                value="{{ $nivel }}" @checked(old('prioridade') === $nivel)>
                            <label for="prio-{{ $classe }}" class="{{ $classe }}">
                                <i class="bi {{ $icone }}"></i>
                                <span>{{ $nivel }}</span>
                            </label>
                        @endforeach
                    </div>
                    <span class="rm-field-error" data-erro-for="prioridade">{{ $errors->first('prioridade') }}</span>
                </div>

                <div class="mb-3">
                    <label for="descricao" class="form-label rm-form-label">Descrição do defeito</label>
                    <textarea class="form-control rm-textarea @error('descricao') is-invalid @enderror" id="descricao" name="descricao"
                        rows="5" maxlength="500" placeholder="Descreva o que está acontecendo" required>{{ old('descricao') }}</textarea>
                    <div class="d-flex justify-content-between gap-2">
                        <span class="rm-field-error"
                            data-erro-for="descricao">{{ $errors->first('descricao') }}</span>
                        <small class="text-muted" id="contador">0/500</small>
                    </div>
                </div>

                <div class="rm-form-actions">
                    <button type="button" id="btnLimpar" class="btn rm-btn-clear">
                        <i class="bi bi-eraser-fill me-1"></i> Limpar
                    </button>
                    <button type="submit" id="btnEnviar" class="btn btn-rm-submit">
                        <i class="bi bi-send-fill me-1"></i> Registrar O.S
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="@assetVersionado('js/os.js')"></script>
@endpush
