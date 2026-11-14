
@extends('layouts.app')

@section('title', 'Pedidos de Reposição - RadarMind')

@push('styles')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="@assetVersionado('css/pedidos-reposicao.css')">
@endpush

@section('content')
    <div class="rm-pedidos container py-4 py-md-5" id="pedidosApp" data-url-lista="{{ route('pedidos-reposicao.listar') }}"
        data-url-pedidos="{{ route('pedidos-reposicao.store') }}" data-eh-matriz="{{ $ehMatriz ? '1' : '0' }}"
        data-item-sugerido="{{ $itemSugerido }}" data-pedido-sugerido="{{ $pedidoSugerido }}">

        {{-- Itens de estoque de cada unidade: o JS mostra só os da unidade escolhida --}}
        <script type="application/json" id="itensPorUnidade">@json($itens)</script>
        <script type="application/json" id="statusLista">@json($statusLista)</script>

        <div class="mb-3">
            <a href="{{ route('home') }}" class="rm-panel-header-link"><i class="bi bi-arrow-left"></i> Voltar ao
                dashboard</a>
        </div>

        <div class="page-heading mb-4">
            <p class="eyebrow mb-2">RADARMIND / ESTOQUE</p>
            <h1>Pedidos de Reposição</h1>

        </div>

        <div id="feedback" class="alert d-none" role="status" aria-live="polite"></div>

        <section class="form-card">
            <div class="card-heading">
                <div>
                    <h2>Novo pedido</h2>
                    <p class="mb-0">Preencha as informações abaixo para registrar a reposição.</p>
                </div>
                <span class="status-pill"><span class="status-dot"></span> Novo registro</span>
            </div>
            <form id="pedidoForm" class="p-4 p-md-5">
                <div class="section-title"><span>01</span> Dados da solicitação</div>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label for="solicitante" class="form-label">Solicitante <span>*</span></label>
                        <input id="solicitante" name="solicitante" class="form-control" maxlength="120"
                            placeholder="Nome de quem está solicitando" required>
                    </div>
                    <div class="col-md-6">
                        <label for="data_pedido" class="form-label">Data <span>*</span></label>
                        <input id="data_pedido" name="data_pedido" type="date" class="form-control"
                            value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="unit_id" class="form-label">Reposição para (unidade) <span>*</span></label>
                        <select id="unit_id" name="unit_id" class="form-select" data-unidade required
                            @disabled(!$ehMatriz)>
                            @foreach ($unidades as $unidade)
                                <option value="{{ $unidade->id }}" @selected($unidade->id === $minhaUnidadeId)>
                                    {{ $unidade->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="item_id" class="form-label">Item para repor <span>*</span></label>
                        <select id="item_id" name="item_id" class="form-select" data-item required></select>
                    </div>
                    <div class="col-md-3">
                        <label for="quantidade" class="form-label">Quantidade para repor <span>*</span></label>
                        <input id="quantidade" name="quantidade" type="number" min="1" step="1"
                            class="form-control" placeholder="Ex.: 25" required>
                    </div>
                </div>

                <div class="section-title"><span>02</span> Responsável pelo registro</div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="responsavel_nome" class="form-label">Nome do responsável <span>*</span></label>
                        <input id="responsavel_nome" name="responsavel_nome" class="form-control" maxlength="120"
                            value="{{ auth()->user()->name }}" placeholder="Nome completo" required>
                    </div>
                    <div class="col-md-6">
                        <label for="observacao" class="form-label">Observação</label>
                        <input id="observacao" name="observacao" class="form-control" maxlength="1000"
                            placeholder="Opcional">
                    </div>
                </div>

                <div class="form-footer mt-5">
                    <p class="required-note mb-0"><span>*</span> Campos obrigatórios</p>
                    <button class="btn btn-submit" type="submit" id="submitBtn">Registrar pedido <span
                            aria-hidden="true">→</span></button>
                </div>
            </form>
        </section>

        <section class="list-card mt-4">
            <div class="list-heading">
                <div>
                    <h2>Pedidos registrados</h2>
                    <p class="mb-0">
                        {{ $ehMatriz ? 'Pedidos da matriz e de todas as subempresas.' : 'Pedidos da sua unidade.' }}
                    </p>
                </div>
                <button class="btn btn-refresh" id="refreshBtn" type="button">Atualizar lista</button>
            </div>
            <div id="pedidosLista" class="table-responsive">
                <div class="empty-state">Carregando pedidos...</div>
            </div>
        </section>

        <div class="modal fade" id="editarModal" tabindex="-1" aria-labelledby="editarTitulo" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form id="editarForm">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="editarTitulo">Editar pedido nº <span id="editarId"></span>
                            </h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div id="editarErro" class="alert alert-danger d-none" role="alert"></div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit_solicitante" class="form-label">Solicitante <span>*</span></label>
                                    <input id="edit_solicitante" name="solicitante" class="form-control" maxlength="120"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_data_pedido" class="form-label">Data <span>*</span></label>
                                    <input id="edit_data_pedido" name="data_pedido" type="date" class="form-control"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_unit_id" class="form-label">Reposição para (unidade)
                                        <span>*</span></label>
                                    <select id="edit_unit_id" name="unit_id" class="form-select" data-unidade required
                                        @disabled(!$ehMatriz)>
                                        @foreach ($unidades as $unidade)
                                            <option value="{{ $unidade->id }}">{{ $unidade->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_item_id" class="form-label">Item para repor <span>*</span></label>
                                    <select id="edit_item_id" name="item_id" class="form-select" data-item
                                        required></select>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_quantidade" class="form-label">Quantidade <span>*</span></label>
                                    <input id="edit_quantidade" name="quantidade" type="number" min="1"
                                        step="1" class="form-control" required>
                                </div>
                                <div class="col-md-8">
                                    <label for="edit_responsavel_nome" class="form-label">Nome do responsável
                                        <span>*</span></label>
                                    <input id="edit_responsavel_nome" name="responsavel_nome" class="form-control"
                                        maxlength="120" required>
                                </div>
                                <div class="col-12">
                                    <label for="edit_observacao" class="form-label">Observação</label>
                                    <input id="edit_observacao" name="observacao" class="form-control" maxlength="1000">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-refresh" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-submit" id="editarSalvar">Salvar alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ===== Confirmar chegada da reposição (status "Atendido") =====
             Ao confirmar: a quantidade entra no estoque da unidade, a entrada vai para Movimentações,
             o assinante aparece na coluna "Assinante" e o alerta "Pedido de estoque atendido" é criado. --}}
        <div class="modal fade rm-chegada-modal" id="chegadaModal" tabindex="-1" aria-labelledby="chegadaTitulo"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="chegadaForm" novalidate>
                        <div class="rm-chegada-topo">
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Fechar"></button>

                            <h2 class="rm-chegada-titulo" id="chegadaTitulo">Confirmar chegada do estoque</h2>
                            <p class="rm-chegada-subtitulo">Pedido nº <span id="chegadaPedido"></span></p>
                        </div>

                        <div class="rm-chegada-corpo">
                            <div id="chegadaErro" class="alert alert-danger d-none" role="alert"></div>

                            <div class="rm-chegada-resumo">
                                <div class="rm-chegada-info">
                                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                                    <div>
                                        <span>Unidade</span>
                                        <strong id="chegadaUnidade"></strong>
                                    </div>
                                </div>
                                <div class="rm-chegada-info">
                                    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                                    <div>
                                        <span>Item</span>
                                        <strong id="chegadaItem"></strong>
                                    </div>
                                </div>
                                <div class="rm-chegada-info rm-chegada-qtd">
                                    <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
                                    <div>
                                        <span>Quantidade que chegou</span>
                                        <strong>+<span id="chegadaQuantidade"></span> un.</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="rm-chegada-saldo" id="chegadaSaldo">
                                <div>
                                    <span>Saldo atual</span>
                                    <strong id="chegadaSaldoAtual"></strong>
                                </div>
                                <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
                                <div>
                                    <span>Depois da chegada</span>
                                    <strong id="chegadaSaldoNovo"></strong>
                                </div>
                            </div>

                            <label for="chegadaAssinante" class="rm-chegada-label">
                                <i class="fa-solid fa-signature" aria-hidden="true"></i> Assinatura de recebimento
                                <span>*</span>
                            </label>
                            <input id="chegadaAssinante" name="assinante" class="form-control rm-chegada-assinatura"
                                maxlength="120" autocomplete="name" placeholder="Nome de quem recebeu a mercadoria"
                                required>
                            <p class="rm-chegada-dica">
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                Ao confirmar, a quantidade entra no estoque da unidade, a entrada é registrada em
                                Movimentações e o pedido não poderá mais ser alterado.
                            </p>
                        </div>

                        <div class="rm-chegada-rodape">
                            <button type="button" class="btn btn-refresh" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-submit" id="chegadaConfirmar">
                                <i class="fa-solid fa-check" aria-hidden="true"></i> Confirmar chegada do estoque
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script defer src="@assetVersionado('js/pedidos-reposicao.js')"></script>
@endpush
