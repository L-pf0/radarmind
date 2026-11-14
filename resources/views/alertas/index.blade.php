{{-- resources/views/alertas/index.blade.php --}}
{{-- Central de Notificações / Alertas (módulo feito pelo grupo em HTML/CSS/JS + Python/Flask).
     Os dados vêm do AlertaController: a matriz vê todas as unidades, a subempresa só a dela. --}}
@extends('layouts.app')

@section('title', 'Notificações / Alertas - RadarMind')

@push('styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="@assetVersionado('css/alertas.css')">
@endpush

@section('content')
    <div class="rm-central-alertas" id="centralAlertas" data-url-contexto="{{ route('alertas.contexto') }}"
        data-url-lista="{{ route('alertas.listar') }}" data-url-marcar-todos="{{ route('alertas.marcar-todos') }}"
        data-url-alertas="{{ url('alertas') }}">

        <div class="rm-pagina">

            <!-- Título, perfil e "marcar todos" -->
            <div class="rm-titulo-linha">
                <div>
                    <br>
                    <h1 class="rm-titulo">
                        Alertas
                        <span class="badge bg-success rounded-pill" id="badge-perfil"></span>
                    </h1>
                    <br>

                </div>
                <button type="button" class="rm-link" id="btn-marcar-todos">Marcar todos como lidos</button>
            </div>

            <section class="rm-cartao">

                <!-- Abas de categoria -->
                <nav class="rm-abas" id="abas" aria-label="Categorias de alertas">
                    <button type="button" class="rm-aba ativa" data-categoria="TODOS" aria-current="true">Todos</button>
                    <button type="button" class="rm-aba" data-categoria="ESTOQUE">Estoque</button>
                    <button type="button" class="rm-aba" data-categoria="PEDIDOS">Pedidos</button>
                    <button type="button" class="rm-aba" data-categoria="MAQUINARIO">Maquinário</button>
                    <button type="button" class="rm-aba" data-categoria="MANUTENCAO">Manutenção</button>
                </nav>

                <!-- Filtros -->
                <div class="rm-filtros">
                    <!-- Só a Matriz vê este filtro; para a subempresa o JavaScript remove o campo -->
                    <div class="rm-campo" id="campo-unidade" hidden>
                        <label for="filtro-unidade">Unidade</label>
                        <select class="form-select" id="filtro-unidade">
                            <option value="">(Todas as Subempresas)</option>
                        </select>
                    </div>

                    <div class="rm-campo">
                        <label for="filtro-gravidade">Gravidade</label>
                        <select class="form-select" id="filtro-gravidade">
                            <option value="">(Todas)</option>
                            <option value="CRITICO">Crítico</option>
                            <option value="ALERTA">Alerta</option>
                            <option value="CONCLUIDO">Concluído</option>
                        </select>
                    </div>

                    <div class="rm-periodo">
                        <button type="button" class="rm-calendario" id="btn-calendario" aria-expanded="false"
                            aria-controls="painel-periodo" aria-label="Filtrar por período">
                            <i class="bi bi-calendar" aria-hidden="true"></i>
                        </button>
                        <div class="rm-periodo-painel" id="painel-periodo" hidden>
                            <label for="data-inicio">De</label>
                            <input type="date" class="form-control" id="data-inicio">
                            <label for="data-fim">Até</label>
                            <input type="date" class="form-control" id="data-fim">
                            <button type="button" class="rm-link" id="btn-limpar-periodo">Limpar período</button>
                        </div>
                    </div>
                </div>

                <div id="aviso" aria-live="polite"></div>

                <!-- Lista -->
                <div class="rm-cabecalho-lista" aria-hidden="true">
                    <span class="rm-col-status">Status</span>
                    <span>Detalhes</span>
                </div>
                <div id="lista" class="rm-lista"></div>

                <!-- Rodapé -->
                <div class="rm-rodape">
                    <button type="button" class="rm-link" id="btn-ver-todos" hidden>Ver todos os alertas</button>
                    <div class="rm-paginacao" id="paginacao">
                        <button type="button" id="btn-anterior" aria-label="Página anterior"><i
                                class="bi bi-chevron-left"></i></button>
                        <span id="texto-pagina">Página 1 de 1</span>
                        <button type="button" id="btn-proxima" aria-label="Próxima página"><i
                                class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script defer src="@assetVersionado('js/alertas.js')"></script>
@endpush
