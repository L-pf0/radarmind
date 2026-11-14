// Pedidos de Reposição: tela que conversa com o Laravel em JSON
// (mesmo comportamento do front end original que usava a API Python)

const app = document.getElementById("pedidosApp");
const URL_LISTA = app.dataset.urlLista;
const URL_PEDIDOS = app.dataset.urlPedidos;
const EH_MATRIZ = app.dataset.ehMatriz === "1";
const ITEM_SUGERIDO = Number(app.dataset.itemSugerido) || null;
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const ITENS = JSON.parse(document.getElementById("itensPorUnidade").textContent);
const STATUS_LISTA = JSON.parse(document.getElementById("statusLista").textContent);

const formulario = document.getElementById("pedidoForm");
const lista = document.getElementById("pedidosLista");
const feedback = document.getElementById("feedback");
const botaoAtualizar = document.getElementById("refreshBtn");
const botaoCadastrar = document.getElementById("submitBtn");

const modalElemento = document.getElementById("editarModal");
const formularioEdicao = document.getElementById("editarForm");
const erroEdicao = document.getElementById("editarErro");
const botaoSalvarEdicao = document.getElementById("editarSalvar");
const modalEdicao = new bootstrap.Modal(modalElemento);

let pedidoEmEdicao = null;

// Faz a requisição ao Laravel já com o token CSRF e pedindo resposta em JSON
async function requisitar(url, metodo = "GET", corpo = null) {
  const opcoes = {
    method: metodo,
    headers: {
      "Accept": "application/json",
      "X-CSRF-TOKEN": CSRF_TOKEN,
      "X-Requested-With": "XMLHttpRequest"
    }
  };

  if (corpo) {
    opcoes.headers["Content-Type"] = "application/json";
    opcoes.body = JSON.stringify(corpo);
  }

  const resposta = await fetch(url, opcoes);
  const resultado = await resposta.json().catch(() => ({}));

  if (!resposta.ok) {
    throw new Error(mensagemDeErro(resultado));
  }

  return resultado;
}

// Junta as mensagens de validação do Laravel em um texto só
function mensagemDeErro(resultado) {
  if (resultado.errors) {
    return Object.values(resultado.errors).flat().join(" ");
  }

  return resultado.erro || resultado.message || "Não foi possível concluir a operação.";
}

// Exibir mensagens na tela
function mostrarMensagem(texto, tipo = "success") {
  feedback.textContent = texto;
  feedback.className = `alert alert-${tipo}`;
}

// Formatar data para o padrão brasileiro
function formatarData(data) {
  if (!data) return "";

  const partes = String(data).slice(0, 10).split("-");

  if (partes.length !== 3) return data;

  return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

// Mostra no select de item só os itens do estoque da unidade escolhida
function preencherItens(form, itemSelecionado = null) {
  const unidade = Number(form.querySelector("[data-unidade]").value);
  const selectItem = form.querySelector("[data-item]");
  const itensDaUnidade = ITENS.filter((item) => item.unit_id === unidade);

  selectItem.replaceChildren();

  if (itensDaUnidade.length === 0) {
    selectItem.add(new Option("Nenhum item no estoque desta unidade", ""));
    return;
  }

  itensDaUnidade.forEach((item) => selectItem.add(new Option(item.texto, item.id)));

  if (itemSelecionado && itensDaUnidade.some((item) => item.id === itemSelecionado)) {
    selectItem.value = itemSelecionado;
  }
}

[formulario, formularioEdicao].forEach((form) => {
  form.querySelector("[data-unidade]").addEventListener("change", () => preencherItens(form));
});

// Colunas da tabela de pedidos (também viram o rótulo de cada célula no celular)
const COLUNAS = ["ID", "Solicitante", "Data", "Unidade", "Item", "Quantidade", "Responsável", "Status", "Assinante", "Ações"];

// ---------------------------------------------------------------------------
// Modal "Confirmar chegada do estoque" (status Atendido): pede o nome do assinante
// ---------------------------------------------------------------------------
const PEDIDO_SUGERIDO = Number(app.dataset.pedidoSugerido) || null;
const chegadaElemento = document.getElementById("chegadaModal");
const chegadaModal = new bootstrap.Modal(chegadaElemento);
const chegadaForm = document.getElementById("chegadaForm");
const chegadaErro = document.getElementById("chegadaErro");
const chegadaAssinante = document.getElementById("chegadaAssinante");
const chegadaConfirmar = document.getElementById("chegadaConfirmar");

let pedidoEmChegada = null; // pedido aberto no modal
let selectEmChegada = null; // select de status que abriu o modal (volta ao valor anterior se cancelar)
let chegadaConfirmada = false;
let pedidoSugeridoAberto = false;

function abrirConfirmacaoChegada(pedido, select = null) {
  pedidoEmChegada = pedido;
  selectEmChegada = select;
  chegadaConfirmada = false;

  const item = ITENS.find((i) => i.id === pedido.item_id);

  document.getElementById("chegadaPedido").textContent = pedido.id;
  document.getElementById("chegadaUnidade").textContent = pedido.responsavel_unidade ?? "—";
  document.getElementById("chegadaItem").textContent = pedido.item ?? "—";
  document.getElementById("chegadaQuantidade").textContent = pedido.quantidade;

  // Saldo antes e depois (só quando o item está na lista de itens da página)
  document.getElementById("chegadaSaldo").hidden = !item;
  if (item) {
    document.getElementById("chegadaSaldoAtual").textContent = `${item.saldo} un.`;
    document.getElementById("chegadaSaldoNovo").textContent = `${item.saldo + pedido.quantidade} un.`;
  }

  chegadaAssinante.value = "";
  chegadaAssinante.classList.remove("is-invalid");
  chegadaErro.classList.add("d-none");
  chegadaModal.show();
}

chegadaElemento.addEventListener("shown.bs.modal", () => chegadaAssinante.focus());

// Fechou sem confirmar: o select volta para o status que estava
chegadaElemento.addEventListener("hidden.bs.modal", () => {
  if (!chegadaConfirmada && selectEmChegada && pedidoEmChegada) {
    selectEmChegada.value = pedidoEmChegada.status;
  }
});

chegadaForm.addEventListener("submit", async (evento) => {
  evento.preventDefault();

  const assinante = chegadaAssinante.value.trim();
  if (!assinante) {
    chegadaAssinante.classList.add("is-invalid");
    chegadaErro.textContent = "Informe o nome de quem confirmou a chegada da reposição.";
    chegadaErro.classList.remove("d-none");
    chegadaAssinante.focus();
    return;
  }

  chegadaConfirmar.disabled = true;

  try {
    const resultado = await requisitar(`${URL_PEDIDOS}/${pedidoEmChegada.id}/status`, "PATCH", {
      status: "Atendido",
      assinante,
    });

    // O saldo do item mudou: atualiza a lista local (usada no formulário e no próximo modal)
    const item = ITENS.find((i) => i.id === pedidoEmChegada.item_id);
    if (item) {
      item.saldo += pedidoEmChegada.quantidade;
      item.texto = item.texto.replace(/\(saldo \d+\)$/, `(saldo ${item.saldo})`);
    }

    chegadaConfirmada = true;
    chegadaModal.hide();
    mostrarMensagem(resultado.mensagem, "success");
    await carregarPedidos();
  } catch (erro) {
    chegadaErro.textContent = erro.message;
    chegadaErro.classList.remove("d-none");
  } finally {
    chegadaConfirmar.disabled = false;
  }
});

// Coluna "Assinante": quem confirmou a chegada da reposição (e quando)
function celulaAssinante(pedido) {
  const celula = document.createElement("td");
  celula.dataset.label = "Assinante";

  if (!pedido.assinante) {
    celula.textContent = "—";
    return celula;
  }

  const nome = document.createElement("span");
  nome.className = "rm-assinante";
  nome.innerHTML = '<i class="fa-solid fa-signature" aria-hidden="true"></i> ';
  nome.append(pedido.assinante);
  celula.appendChild(nome);

  if (pedido.chegada_em) {
    const quando = document.createElement("small");
    quando.className = "rm-assinante-data";
    quando.textContent = pedido.chegada_em;
    celula.appendChild(quando);
  }

  return celula;
}

// Criar uma célula da tabela de pedidos
function criarCelula(valor, rotulo) {
  const celula = document.createElement("td");
  celula.textContent = valor ?? "";
  celula.dataset.label = rotulo;
  return celula;
}

// Status: a matriz troca direto na lista; a subempresa vê o status e confirma a chegada dos pedidos dela
function celulaStatus(pedido) {
  const celula = document.createElement("td");
  celula.dataset.label = "Status";

  if (!pedido.pode_mudar_status) {
    const etiqueta = document.createElement("span");
    etiqueta.className = `rm-pedido-status status-${pedido.status === "Em aberto" ? "aberto" : pedido.status === "Atendido" ? "atendido" : "cancelado"}`;
    etiqueta.textContent = pedido.status;
    if (pedido.estoque_lancado) {
      etiqueta.title = "A reposição chegou e a quantidade já entrou no estoque";
    }
    celula.appendChild(etiqueta);

    // Subempresa: a mercadoria chegou na unidade dela → abre o modal com a assinatura de recebimento
    if (pedido.pode_confirmar_chegada) {
      const botao = document.createElement("button");
      botao.type = "button";
      botao.className = "btn btn-sm rm-btn-chegada";
      botao.innerHTML = '<i class="fa-solid fa-truck-ramp-box" aria-hidden="true"></i> Confirmar chegada';
      botao.addEventListener("click", () => abrirConfirmacaoChegada(pedido));
      celula.appendChild(botao);
    }

    return celula;
  }

  const select = document.createElement("select");
  select.className = "form-select form-select-sm rm-pedido-status-select";
  select.setAttribute("aria-label", `Status do pedido nº ${pedido.id}`);
  STATUS_LISTA.forEach((status) => select.add(new Option(status, status, false, status === pedido.status)));

  select.addEventListener("change", async () => {
    // "Atendido" = a reposição chegou: abre o modal que pede o nome do assinante
    if (select.value === "Atendido") {
      abrirConfirmacaoChegada(pedido, select);
      return;
    }

    select.disabled = true;
    try {
      const resultado = await requisitar(`${URL_PEDIDOS}/${pedido.id}/status`, "PATCH", { status: select.value });
      mostrarMensagem(resultado.mensagem, "success");
      await carregarPedidos();
    } catch (erro) {
      mostrarMensagem(erro.message, "danger");
      select.value = pedido.status;
      select.disabled = false;
    }
  });

  celula.appendChild(select);
  return celula;
}

// Ler os campos de um formulário no formato esperado pelo servidor
function lerPedido(form) {
  const dados = new FormData(form);

  return {
    solicitante: dados.get("solicitante")?.trim(),
    data_pedido: dados.get("data_pedido"),
    unit_id: Number(form.querySelector("[data-unidade]").value),
    item_id: Number(dados.get("item_id")),
    quantidade: Number(dados.get("quantidade")),
    responsavel_nome: dados.get("responsavel_nome")?.trim(),
    observacao: dados.get("observacao")?.trim() || null
  };
}

// Carregar os pedidos cadastrados
async function carregarPedidos() {
  lista.innerHTML = '<div class="empty-state">Carregando pedidos...</div>';

  try {
    const resultado = await requisitar(URL_LISTA);
    const pedidos = resultado.data ?? [];

    lista.replaceChildren();

    if (pedidos.length === 0) {
      const vazio = document.createElement("div");
      vazio.className = "empty-state";
      vazio.textContent = "Nenhum pedido cadastrado até o momento.";
      lista.appendChild(vazio);
      return;
    }

    const tabela = document.createElement("table");
    tabela.className = "table table-hover align-middle mb-0 rm-pedidos-tabela";

    const cabecalho = document.createElement("thead");
    cabecalho.className = "table-light";

    const linhaCabecalho = document.createElement("tr");

    COLUNAS.forEach((coluna) => {
      const th = document.createElement("th");
      th.scope = "col";
      th.textContent = coluna;
      linhaCabecalho.appendChild(th);
    });

    cabecalho.appendChild(linhaCabecalho);
    tabela.appendChild(cabecalho);

    const corpo = document.createElement("tbody");

    pedidos.forEach((pedido) => {
      const linha = document.createElement("tr");

      linha.appendChild(criarCelula(pedido.id, COLUNAS[0]));
      linha.appendChild(criarCelula(pedido.solicitante, COLUNAS[1]));
      linha.appendChild(criarCelula(formatarData(pedido.data_pedido), COLUNAS[2]));
      linha.appendChild(criarCelula(pedido.responsavel_unidade, COLUNAS[3]));
      linha.appendChild(criarCelula(pedido.item, COLUNAS[4]));
      linha.appendChild(criarCelula(pedido.quantidade, COLUNAS[5]));
      linha.appendChild(criarCelula(pedido.responsavel_nome, COLUNAS[6]));
      linha.appendChild(celulaStatus(pedido));
      linha.appendChild(celulaAssinante(pedido));

      const celulaAcoes = document.createElement("td");
      celulaAcoes.className = "rm-pedidos-acoes";
      celulaAcoes.dataset.label = COLUNAS[9];

      if (pedido.pode_editar) {
        const grupoBotoes = document.createElement("div");
        grupoBotoes.className = "d-flex gap-2";

        const botaoEditar = document.createElement("button");
        botaoEditar.type = "button";
        botaoEditar.className = "btn btn-editar btn-sm";
        botaoEditar.textContent = "Editar";
        botaoEditar.addEventListener("click", () => abrirEdicao(pedido));

        const botaoExcluir = document.createElement("button");
        botaoExcluir.type = "button";
        botaoExcluir.className = "btn btn-danger btn-sm";
        botaoExcluir.textContent = "Excluir";
        botaoExcluir.addEventListener("click", () => excluirPedido(pedido.id, botaoExcluir));

        grupoBotoes.appendChild(botaoEditar);
        grupoBotoes.appendChild(botaoExcluir);
        celulaAcoes.appendChild(grupoBotoes);
      } else {
        celulaAcoes.textContent = "—";
      }

      linha.appendChild(celulaAcoes);
      corpo.appendChild(linha);
    });

    tabela.appendChild(corpo);
    lista.appendChild(tabela);

    // Veio de um alerta ou do Estoque (?pedido=ID): quem pode confirmar a chegada já cai no modal
    if (PEDIDO_SUGERIDO && !pedidoSugeridoAberto) {
      pedidoSugeridoAberto = true;
      const sugerido = pedidos.find((p) => p.id === PEDIDO_SUGERIDO);
      if (sugerido && sugerido.pode_confirmar_chegada) {
        abrirConfirmacaoChegada(sugerido);
      }
    }
  } catch (erro) {
    console.error("Erro ao carregar pedidos:", erro);

    lista.replaceChildren();

    const mensagemErro = document.createElement("div");
    mensagemErro.className = "empty-state";
    mensagemErro.textContent = "Não foi possível carregar os pedidos. Verifique a conexão com o banco de dados.";
    lista.appendChild(mensagemErro);
  }
}

// Cadastrar um novo pedido
formulario.addEventListener("submit", async (evento) => {
  evento.preventDefault();

  botaoCadastrar.disabled = true;
  botaoCadastrar.textContent = "Registrando...";

  try {
    const resultado = await requisitar(URL_PEDIDOS, "POST", lerPedido(formulario));

    const unidadeEscolhida = formulario.querySelector("[data-unidade]").value;
    formulario.reset();
    formulario.querySelector("[data-unidade]").value = unidadeEscolhida;
    preencherItens(formulario);
    mostrarMensagem(resultado.mensagem || "Pedido registrado com sucesso!", "success");

    await carregarPedidos();
  } catch (erro) {
    console.error("Erro ao cadastrar pedido:", erro);
    mostrarMensagem(erro.message || "Erro ao registrar pedido.", "danger");
  } finally {
    botaoCadastrar.disabled = false;
    botaoCadastrar.innerHTML = 'Registrar pedido <span aria-hidden="true">→</span>';
  }
});

// Excluir um pedido
async function excluirPedido(id, botao) {
  const confirmar = window.confirm(
    `Tem certeza que deseja excluir o pedido nº ${id}? Essa ação não pode ser desfeita.`
  );

  if (!confirmar) return;

  botao.disabled = true;
  botao.textContent = "Excluindo...";

  try {
    const resultado = await requisitar(`${URL_PEDIDOS}/${id}`, "DELETE");

    mostrarMensagem(resultado.mensagem || "Pedido excluído com sucesso!", "success");

    await carregarPedidos();
  } catch (erro) {
    console.error("Erro ao excluir pedido:", erro);
    mostrarMensagem(erro.message || "Erro ao excluir o pedido.", "danger");

    botao.disabled = false;
    botao.textContent = "Excluir";
  }
}

// Abrir a janela de edição com os dados do pedido
function abrirEdicao(pedido) {
  pedidoEmEdicao = pedido.id;

  document.getElementById("editarId").textContent = pedido.id;
  document.getElementById("edit_solicitante").value = pedido.solicitante ?? "";
  document.getElementById("edit_data_pedido").value = String(pedido.data_pedido ?? "").slice(0, 10);
  document.getElementById("edit_unit_id").value = pedido.unit_id ?? "";
  preencherItens(formularioEdicao, pedido.item_id);
  document.getElementById("edit_quantidade").value = pedido.quantidade ?? "";
  document.getElementById("edit_responsavel_nome").value = pedido.responsavel_nome ?? "";
  document.getElementById("edit_observacao").value = pedido.observacao ?? "";

  erroEdicao.classList.add("d-none");
  modalEdicao.show();
}

// Salvar as alterações do pedido
formularioEdicao.addEventListener("submit", async (evento) => {
  evento.preventDefault();

  botaoSalvarEdicao.disabled = true;
  botaoSalvarEdicao.textContent = "Salvando...";

  try {
    const resultado = await requisitar(`${URL_PEDIDOS}/${pedidoEmEdicao}`, "PUT", lerPedido(formularioEdicao));

    modalEdicao.hide();
    mostrarMensagem(resultado.mensagem || "Pedido atualizado com sucesso!", "success");

    await carregarPedidos();
  } catch (erro) {
    console.error("Erro ao editar pedido:", erro);

    erroEdicao.textContent = erro.message || "Erro ao editar o pedido.";
    erroEdicao.classList.remove("d-none");
  } finally {
    botaoSalvarEdicao.disabled = false;
    botaoSalvarEdicao.textContent = "Salvar alterações";
  }
});

// Botão para atualizar a lista manualmente
botaoAtualizar.addEventListener("click", carregarPedidos);

// Item vindo de outra tela (ex.: ?item=12): já deixa a unidade e o item escolhidos
const sugerido = ITENS.find((item) => item.id === ITEM_SUGERIDO);
if (sugerido && EH_MATRIZ) {
  formulario.querySelector("[data-unidade]").value = sugerido.unit_id;
}
preencherItens(formulario, sugerido?.id ?? null);

// Carregar a lista assim que a página abrir
carregarPedidos();
