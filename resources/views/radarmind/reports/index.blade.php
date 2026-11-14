@extends(config('radarmind.layout'))
@section('title', 'Emissão de relatórios · RadarMind')

@push('styles')
    <link rel="stylesheet" href="@assetVersionado('css/relatorio-impressao.css')">
@endpush

@php
    $tipoEscolhido = old('type', request('type', 'estoque'));

    // Cada card diz exatamente o que o relatório traz
    $relatorios = [
        'estoque' => [
            'titulo' => 'Estoque', 'icone' => 'fa-boxes-stacked', 'cor' => 'green',
            'itens' => ['Resumo de peças e maquinário', 'Itens que precisam de reposição e quanto repor', 'Localização, lote, série, giro e status'],
        ],
        'movimentacoes' => [
            'titulo' => 'Movimentações', 'icone' => 'fa-right-left', 'cor' => 'blue',
            'itens' => ['Entradas, saídas e saldo do período', 'Totais por item e por unidade', 'Responsável e observações de cada registro'],
        ],
        'desempenho' => [
            'titulo' => 'Desempenho', 'icone' => 'fa-chart-column', 'cor' => 'green',
            'itens' => ['Ranking das unidades com a pontuação', 'Movimentações e itens de alto giro', 'Alertas, O.S. e pedidos em aberto'],
        ],
    ];
@endphp

@section('content')
    <h1>Emissão de relatórios</h1>

    <form method="GET" class="rm-relatorios-form" novalidate>
        <input id="report-type" type="hidden" name="type" value="{{ $tipoEscolhido }}">

        <p class="rm-relatorios-passo"><span>1</span> Escolha o relatório</p>
        <section class="grid g3">
            @foreach ($relatorios as $tipo => $relatorio)
                <button type="button" @class(['card', 'pick', 'selected' => $tipoEscolhido === $tipo]) data-report="{{ $tipo }}"
                    aria-pressed="{{ $tipoEscolhido === $tipo ? 'true' : 'false' }}">
                    <div class="tile {{ $relatorio['cor'] }}"><i class="fa-solid {{ $relatorio['icone'] }}" aria-hidden="true"></i></div>
                    <h3>{{ $relatorio['titulo'] }}</h3>
                    <ul class="checks">
                        @foreach ($relatorio['itens'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </button>
            @endforeach
        </section>

        <section class="card mt">
            <p class="rm-relatorios-passo"><span>2</span> Defina os parâmetros</p>
            <div class="fields">
                <div>
                    <label for="relatorio-unidade">Unidade</label>
                    <select id="relatorio-unidade" name="unit_id">
                        <option value="">Todas as unidades (consolidado)</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" @selected((string) old('unit_id', request('unit_id')) === (string) $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- O período só vale para o relatório de movimentações --}}
                <div @class(['hidden' => $tipoEscolhido !== 'movimentacoes']) data-somente-para="movimentacoes">
                    <label for="relatorio-inicio">Data inicial</label>
                    <input id="relatorio-inicio" name="start_date" type="date" value="{{ old('start_date', request('start_date')) }}">
                </div>
                <div @class(['hidden' => $tipoEscolhido !== 'movimentacoes']) data-somente-para="movimentacoes">
                    <label for="relatorio-fim">Data final</label>
                    <input id="relatorio-fim" name="end_date" type="date" value="{{ old('end_date', request('end_date')) }}">
                </div>
            </div>
            <p @class(['field-hint', 'hidden' => $tipoEscolhido !== 'movimentacoes']) data-somente-para="movimentacoes">
                Deixe as datas em branco para incluir todas as movimentações.
            </p>

            <p class="rm-relatorios-passo mt"><span>3</span> Escolha como receber</p>
            <div class="rm-relatorios-saidas">
                <button class="rm-relatorios-saida" formaction="{{ route('radarmind.reports.preview') }}">
                    <span class="rm-relatorios-saida-icone pdf"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i></span>
                    <span>
                        <strong>Visualizar / imprimir PDF</strong>
                        <small>Abre o relatório na tela. Use "Imprimir / salvar PDF" para guardar.</small>
                    </span>
                </button>
                <button class="rm-relatorios-saida" formaction="{{ route('radarmind.reports.export') }}">
                    <span class="rm-relatorios-saida-icone csv"><i class="fa-solid fa-file-csv" aria-hidden="true"></i></span>
                    <span>
                        <strong>Baixar CSV (Excel)</strong>
                        <small>Arquivo organizado em blocos: resumo e uma tabela por assunto.</small>
                    </span>
                </button>
            </div>
        </section>
    </form>
@endsection
