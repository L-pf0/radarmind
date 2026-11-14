@extends(config('radarmind.layout'))
@section('title', $relatorio->titulo.' · RadarMind')

@push('styles')
    {{-- Tela e impressão: a tabela se ajusta à largura do papel escolhido, sem barra de rolagem --}}
    <link rel="stylesheet" href="@assetVersionado('css/relatorio-impressao.css')">
@endpush

@use('App\Support\Relatorios\SecaoDoRelatorio')

@php
    $icones = ['estoque' => 'fa-boxes-stacked', 'movimentacoes' => 'fa-right-left', 'desempenho' => 'fa-chart-column'];
@endphp

@section('content')
    <div class="rm-relatorio" data-tipo="{{ $relatorio->tipo }}">

        {{-- ===== Cabeçalho ===== --}}
        <header class="report-head">
            <div>
                <p class="rm-relatorio-marca">RadarMind · Gerência do Agronegócio</p>
                <h1>{{ $relatorio->titulo }}</h1>
                <p class="sub">{{ $relatorio->descricao }}</p>
                <dl class="rm-relatorio-parametros">
                    @foreach ($relatorio->parametros as $rotulo => $valor)
                        <div><dt>{{ $rotulo }}:</dt> <dd>{{ $valor }}</dd></div>
                    @endforeach
                    <div><dt>Gerado em:</dt> <dd>{{ $relatorio->geradoEm->format('d/m/Y \à\s H:i') }}</dd></div>
                    <div><dt>Por:</dt> <dd>{{ $relatorio->geradoPor }}</dd></div>
                </dl>
            </div>
            <div class="no-print rm-relatorio-acoes">
                <button class="btn" type="button" onclick="window.print()">
                    <i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir / salvar PDF
                </button>
                <a class="btn ghost" href="{{ $linkDoCsv }}">
                    <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Baixar CSV
                </a>
                <a class="btn ghost" href="{{ route('radarmind.reports.index', request()->only('type', 'unit_id', 'start_date', 'end_date')) }}">
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i> Mudar parâmetros
                </a>
            </div>
        </header>

        {{-- ===== Resumo ===== --}}
        <section class="rm-relatorio-resumo" aria-label="Resumo">
            @foreach ($relatorio->resumo as $indicador)
                <div @class(['rm-relatorio-indicador', 'is-'.($indicador['tom'] ?? 'neutro')])>
                    <span class="rm-relatorio-indicador-rotulo">{{ $indicador['rotulo'] }}</span>
                    <strong>{{ is_int($indicador['valor']) ? number_format($indicador['valor'], 0, ',', '.') : $indicador['valor'] }}</strong>
                    <span class="rm-relatorio-indicador-detalhe">{{ $indicador['detalhe'] }}</span>
                </div>
            @endforeach
        </section>

        {{-- ===== Uma tabela por assunto ===== --}}
        @foreach ($relatorio->secoes as $secao)
            @php($colunas = count($secao->colunas))
            <section class="card rm-relatorio-secao" data-colunas="{{ $colunas >= 9 ? 'muitas' : ($colunas >= 6 ? 'medias' : 'poucas') }}">
                <div class="rm-relatorio-secao-topo">
                    <h2><i class="fa-solid {{ $icones[$relatorio->tipo] }}" aria-hidden="true"></i> {{ $secao->titulo }}</h2>
                    <span class="rm-relatorio-contagem">{{ $secao->quantidade() }} {{ $secao->quantidade() === 1 ? 'registro' : 'registros' }}</span>
                </div>
                @if ($secao->descricao !== '')
                    <p class="rm-relatorio-secao-descricao">{{ $secao->descricao }}</p>
                @endif

                @if ($secao->quantidade() === 0)
                    <p class="rm-relatorio-vazio"><i class="fa-regular fa-circle-check" aria-hidden="true"></i> {{ $secao->vazio }}</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    @foreach ($secao->colunas as $rotulo => $tipo)
                                        <th @class(['num' => SecaoDoRelatorio::ehNumerica($tipo)])>{{ $rotulo }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($secao->linhas as $linha)
                                    <tr>
                                        @foreach ($secao->colunas as $rotulo => $tipo)
                                            @php($valor = $linha[$rotulo] ?? null)
                                            <td @class([
                                                'num' => SecaoDoRelatorio::ehNumerica($tipo),
                                                'code' => $tipo === 'codigo',
                                                'rm-negativo' => $tipo === 'saldo' && $valor < 0,
                                                'rm-curto' => SecaoDoRelatorio::ehNumerica($tipo) || in_array($tipo, ['codigo', 'etiqueta'], true) || mb_strlen((string) $valor) <= 12,
                                            ])>
                                                @if ($tipo === 'etiqueta' && $valor !== null)
                                                    <span class="badge {{ SecaoDoRelatorio::classeDaEtiqueta($valor) }}">{{ $valor }}</span>
                                                @else
                                                    {{ SecaoDoRelatorio::paraTela($valor, $tipo) }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            @if ($secao->temTotal())
                                <tfoot>
                                    <tr>
                                        @foreach ($secao->colunas as $rotulo => $tipo)
                                            @php($valor = $secao->total[$rotulo] ?? null)
                                            <td @class(['num' => SecaoDoRelatorio::ehNumerica($tipo), 'rm-curto' => true])>
                                                {{ $valor === null ? '' : SecaoDoRelatorio::paraTela($valor, $tipo) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                @endif
            </section>
        @endforeach

        <p class="rm-relatorio-rodape">
            RadarMind · {{ $relatorio->titulo }} · gerado em {{ $relatorio->geradoEm->format('d/m/Y H:i') }} por {{ $relatorio->geradoPor }}
        </p>
    </div>
@endsection
