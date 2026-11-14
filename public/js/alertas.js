// Front-end da Central de Notificações/Alertas: busca os dados no Laravel (AlertaController) e desenha a tela.

const central = document.getElementById("centralAlertas");
const URLS = {
  contexto: central.dataset.urlContexto,
  lista: central.dataset.urlLista,
  marcarTodos: central.dataset.urlMarcarTodos,
  alertas: central.dataset.urlAlertas, // + "/{id}/lido"
};
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// A central ocupa a tela abaixo da navbar: mede a altura da navbar para o CSS
function medirNavbar() {
  const navbar = document.querySelector(".rm-topbar");
  if (navbar) {
    document.documentElement.style.setProperty("--rm-altura-navbar", `${navbar.offsetHeight}px`);
  }
}
medirNavbar();

let porPagina = 4;

const estado = {
  contexto: null,
  alertas: [],
  categoria: "TODOS",
  pagina: 1,
  verTodos: false,
  aberto: null,
};

const $ = (seletor) => document.querySelector(seletor);
const lista = $("#lista");

const ICONES = {
  CRITICO: {
    icone: "bi-dash-circle",
    classe: "grav-critico",
    texto: "Crítico",
  },
  ALERTA: {
    icone: "bi-exclamation-circle",
    classe: "grav-alerta",
    texto: "Alerta",
  },
  CONCLUIDO: {
    icone: "bi-check-circle",
    classe: "grav-ok",
    texto: "Concluído",
  },
};

function esc(texto) {
  const div = document.createElement("div");
  div.textContent = texto ?? "";
  return div.innerHTML;
}

// Tempo relativo (ex.: "Há 2h") calculado no navegador, para continuar atualizando sozinho
function tempoRelativo(iso) {
  const segundos = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
  const minutos = Math.floor(segundos / 60);
  const horas = Math.floor(segundos / 3600);
  const dias = Math.floor(segundos / 86400);
  if (minutos < 60) return `Há ${Math.max(1, minutos)}min`;
  if (horas <= 48) return `Há ${horas}h`;
  if (dias < 30) return `Há ${dias}d`;
  if (dias < 365) return `Há ${Math.floor(dias / 30)}m`;
  return `Há ${Math.floor(dias / 365)}a`;
}

function atualizarTempos() {
  document.querySelectorAll(".rm-tempo[data-criacao]").forEach((el) => {
    el.textContent = tempoRelativo(el.dataset.criacao);
  });
}

// Calcula quantos alertas cabem por página
const ALTURA_LINHA_PADRAO = 88;
const ALTURA_DETALHE = 190;
function calcularPorPagina() {
  if (!window.matchMedia("(min-width: 768px)").matches) return 4; // celular: 4 por página
  const linha = lista.querySelector(".rm-alerta:not(.aberto)");
  const alturaLinha = linha ? linha.offsetHeight : ALTURA_LINHA_PADRAO;
  const cabem = Math.floor((lista.clientHeight - ALTURA_DETALHE) / alturaLinha);
  return Math.max(2, Math.min(10, cabem));
}

function unidades(n) {
  return `${n} ${n === 1 ? "unidade" : "unidades"}`;
}

async function api(url, opcoes = {}) {
  const resposta = await fetch(url, {
    ...opcoes,
    headers: { Accept: "application/json", "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" },
  });
  const dados = await resposta.json().catch(() => ({}));
  if (!resposta.ok) {
    // Erros de validação do Laravel vêm em "errors"
    const mensagens = dados.errors ? Object.values(dados.errors).flat().join(" ") : null;
    throw new Error(mensagens || dados.erro || dados.message || "Erro ao falar com o servidor.");
  }
  return dados;
}

function mostrarAviso(texto, tipo = "success") {
  $("#aviso").innerHTML = texto
    ? `<div class="alert alert-${tipo} rm-aviso" role="alert">${esc(texto)}</div>`
    : "";
}

// ---------------------------------------------------------------------------
// Início
// ---------------------------------------------------------------------------
async function iniciar() {
  try {
    estado.contexto = await api(URLS.contexto);
    montarCabecalho();
    await carregar();
    desenhar(); // recalcula a paginação
  } catch (erro) {
    console.error("Erro ao montar a Central de Alertas:", erro);
    lista.innerHTML = '<p class="rm-vazio">Não foi possível carregar os alertas. Recarregue a página.</p>';
  }
}

// Selo do perfil, subtítulo e filtro de unidade. Cada parte é opcional na página:
// se o elemento não existir (ex.: o subtítulo foi tirado da tela), a lista continua funcionando.
function montarCabecalho() {
  const { badge, subtitulo, subempresas, usuario } = estado.contexto;

  const selo = $("#badge-perfil");
  if (selo) {
    selo.textContent = badge;
    selo.classList.add(usuario.perfil === "MATRIZ" ? "badge-matriz" : "badge-filial");
  }

  const textoSubtitulo = $("#subtitulo");
  if (textoSubtitulo) textoSubtitulo.textContent = subtitulo;

  const campoUnidade = $("#campo-unidade");
  if (!campoUnidade) return;

  if (usuario.perfil === "MATRIZ") {
    const select = $("#filtro-unidade");
    subempresas.forEach((s) => select.add(new Option(s.nome, s.id)));
    campoUnidade.hidden = false;
  } else {
    campoUnidade.remove(); // subempresa não tem o filtro de unidade
  }
}

async function carregar() {
  const params = new URLSearchParams();
  const unidade = $("#filtro-unidade");
  if (unidade && unidade.value) params.set("subempresa_id", unidade.value);
  if ($("#filtro-gravidade").value)
    params.set("gravidade", $("#filtro-gravidade").value);
  if ($("#data-inicio").value)
    params.set("data_inicio", $("#data-inicio").value);
  if ($("#data-fim").value) params.set("data_fim", $("#data-fim").value);

  try {
    estado.alertas = (await api(URLS.lista + "?" + params.toString())).data ?? [];
    mostrarAviso("");
    desenhar();
  } catch (erro) {
    estado.alertas = [];
    lista.innerHTML = "";
    mostrarAviso(erro.message, "danger");
  }
}

// ---------------------------------------------------------------------------
// Desenho da lista
// ---------------------------------------------------------------------------
function desenhar() {
  // A aba filtra o array que já foi carregado (sem nova chamada ao servidor)
  const filtrados = estado.alertas.filter(
    (a) =>
      estado.categoria === "TODOS" || a.tipo_categoria === estado.categoria,
  );

  porPagina = calcularPorPagina();
  const totalPaginas = Math.max(1, Math.ceil(filtrados.length / porPagina));
  estado.pagina = Math.min(Math.max(1, estado.pagina), totalPaginas);

  const inicio = (estado.pagina - 1) * porPagina;
  const visiveis = estado.verTodos ? filtrados : filtrados.slice(inicio, inicio + porPagina);

  lista.innerHTML = visiveis.length
    ? visiveis.map(htmlAlerta).join("")
    : '<p class="rm-vazio">Nenhum alerta encontrado. Troque a aba ou ajuste os filtros para ver outros.</p>';

  // Rodapé
  const btnVerTodos = $("#btn-ver-todos");
  btnVerTodos.hidden = !(totalPaginas > 1 || estado.verTodos);
  btnVerTodos.textContent = estado.verTodos
    ? "Ver por páginas"
    : "Ver todos os alertas";
  $("#paginacao").hidden = estado.verTodos;
  $("#texto-pagina").textContent = `Página ${estado.pagina} de ${totalPaginas}`;
  $("#btn-anterior").disabled = estado.pagina <= 1;
  $("#btn-proxima").disabled = estado.pagina >= totalPaginas;
}

function htmlAlerta(a) {
  const g = ICONES[a.gravidade] || ICONES.ALERTA;
  const d = a.detalhes;
  const r = a.redirecionamento;
  const aberto = estado.aberto === a.id;

  const bullets = [];
  // A matriz vê todas as unidades: mostra de qual unidade é o alerta
  if (estado.contexto?.usuario.perfil === "MATRIZ" && a.subempresa_nome)
    bullets.push(`<li>Unidade: ${esc(a.subempresa_nome)}</li>`);
  if (d.item_nome) bullets.push(`<li>Item: ${esc(d.item_nome)}</li>`);
  // Pedido atendido: quantidade que chegou e quem assinou o recebimento
  if (d.qtd_recebida) bullets.push(`<li>Quantidade recebida: ${unidades(d.qtd_recebida)}</li>`);
  if (d.assinado_por) bullets.push(`<li>Recebido por (assinatura): ${esc(d.assinado_por)}</li>`);
  // O.S. finalizada: quem tinha pedido a ordem de serviço
  if (d.solicitante) bullets.push(`<li>Solicitado por: ${esc(d.solicitante)}</li>`);
  if (d.item_codigo)
    bullets.push(`<li>Código / ID do Item: ${esc(d.item_codigo)}</li>`);
  bullets.push(
    `<li>Categoria de Giro: ${
      d.alto_giro
        ? "Alto Giro (Demanda Frequente - Reposição Prioritária)"
        : "Giro Normal"
    }</li>`,
  );
  if (d.qtd_atual !== null && d.qtd_minima !== null) {
    bullets.push(
      `<li>Saldo Atual em Estoque: ${unidades(d.qtd_atual)} | Estoque Mínimo Exigido: ${unidades(d.qtd_minima)}</li>`,
    );
  }

  const quando = new Date(a.data_criacao).toLocaleString("pt-BR");

  return `
    <article class="rm-alerta ${a.lido ? "" : "nao-lido"} ${aberto ? "aberto" : ""}" data-id="${a.id}">
      <button type="button" class="rm-linha" aria-expanded="${aberto}" aria-controls="detalhe-${a.id}">
        <span class="rm-col-status">
          <i class="bi ${g.icone} rm-icone ${g.classe}" role="img" aria-label="${g.texto}"></i>
        </span>
        <span class="rm-titulo-alerta">${esc(a.titulo)}</span>
        <span class="rm-tempo" data-criacao="${esc(a.data_criacao)}" title="${esc(quando)}">${esc(tempoRelativo(a.data_criacao))}</span>
        <i class="bi bi-chevron-down rm-seta" aria-hidden="true"></i>
      </button>
      <div class="rm-detalhe" id="detalhe-${a.id}">
        <div class="rm-detalhe-interno">
          <div class="rm-detalhe-corpo">
            <div class="rm-detalhe-texto">
              <strong class="rm-detalhe-titulo">Descrição Detalhada</strong>
              <p>${esc(d.mensagem)}</p>
              <ul>${bullets.join("")}</ul>
            </div>
            <a href="${esc(r.url)}" class="btn ${esc(r.cor_botao)} rm-botao-acao">${esc(r.rotulo_botao)}</a>
          </div>
        </div>
      </div>
    </article>`;
}

// ---------------------------------------------------------------------------
// Eventos
// ---------------------------------------------------------------------------

// Abrir / fechar um alerta (accordion). Ao abrir um alerta não lido, marca como lido.
lista.addEventListener("click", async (evento) => {
  const linha = evento.target.closest(".rm-linha");
  if (!linha) return;

  const cartao = linha.closest(".rm-alerta");
  const id = Number(cartao.dataset.id);
  const abrindo = !cartao.classList.contains("aberto");

  // só um alerta aberto por vez: fecha os outros
  lista.querySelectorAll(".rm-alerta.aberto").forEach((outro) => {
    if (outro !== cartao) {
      outro.classList.remove("aberto");
      outro.querySelector(".rm-linha").setAttribute("aria-expanded", "false");
    }
  });

  cartao.classList.toggle("aberto", abrindo);
  linha.setAttribute("aria-expanded", String(abrindo));
  estado.aberto = abrindo ? id : null;

  const alerta = estado.alertas.find((a) => a.id === id);
  if (abrindo && alerta && !alerta.lido) {
    try {
      await api(`${URLS.alertas}/${id}/lido`, { method: "PATCH" });
      alerta.lido = true;
      cartao.classList.remove("nao-lido");
    } catch (erro) {
      mostrarAviso(erro.message, "danger");
    }

  }
});

// Abas de categoria
$("#abas").addEventListener("click", (evento) => {
  const aba = evento.target.closest(".rm-aba");
  if (!aba) return;
  document.querySelectorAll(".rm-aba").forEach((b) => {
    b.classList.remove("ativa");
    b.removeAttribute("aria-current");
  });
  aba.classList.add("ativa");
  aba.setAttribute("aria-current", "true");
  estado.categoria = aba.dataset.categoria;
  estado.pagina = 1;
  desenhar();
});

// Filtros que consultam o servidor
function filtroMudou() {
  estado.pagina = 1;
  carregar();
}
$("#filtro-gravidade").addEventListener("change", filtroMudou);
if ($("#filtro-unidade"))
  $("#filtro-unidade").addEventListener("change", filtroMudou);

// Calendário (período)
const btnCalendario = $("#btn-calendario");
const painelPeriodo = $("#painel-periodo");

function alternarPeriodo(abrir) {
  painelPeriodo.hidden = !abrir;
  btnCalendario.setAttribute("aria-expanded", String(abrir));
}
btnCalendario.addEventListener("click", () =>
  alternarPeriodo(painelPeriodo.hidden),
);
document.addEventListener("click", (evento) => {
  if (!evento.target.closest(".rm-periodo")) alternarPeriodo(false);
});
document.addEventListener("keydown", (evento) => {
  if (evento.key === "Escape") alternarPeriodo(false);
});


function periodoMudou() {
  const inicio = $("#data-inicio").value;
  const fim = $("#data-fim");
  fim.min = inicio;
  if (inicio && fim.value && fim.value < inicio) fim.value = "";
  btnCalendario.classList.toggle("ativo", Boolean(inicio || fim.value));
  filtroMudou();
}
$("#data-inicio").addEventListener("change", periodoMudou);
$("#data-fim").addEventListener("change", periodoMudou);
$("#btn-limpar-periodo").addEventListener("click", () => {
  $("#data-inicio").value = "";
  $("#data-fim").value = "";
  periodoMudou();
});

// Marcar todos como lidos
$("#btn-marcar-todos").addEventListener("click", async () => {
  try {
    await api(URLS.marcarTodos, { method: "POST" });
    await carregar();
    mostrarAviso("Todos os alertas foram marcados como lidos.");
  } catch (erro) {
    mostrarAviso(erro.message, "danger");
  }
});

// Paginação
$("#btn-anterior").addEventListener("click", () => {
  estado.pagina -= 1;
  desenhar();
});
$("#btn-proxima").addEventListener("click", () => {
  estado.pagina += 1;
  desenhar();
});
$("#btn-ver-todos").addEventListener("click", () => {
  estado.verTodos = !estado.verTodos;
  desenhar();
});

// Tempo relativo e alertas novos atualizam sozinhos, sem apertar F5
setInterval(atualizarTempos, 30000);
setInterval(() => { if (!document.hidden) carregar(); }, 30000);

// Redesenha ao redimensionar a janela
let temporizadorRedimensionar;
window.addEventListener("resize", () => {
  clearTimeout(temporizadorRedimensionar);
  temporizadorRedimensionar = setTimeout(() => {
    medirNavbar();
    desenhar();
  }, 200);
});

iniciar();
