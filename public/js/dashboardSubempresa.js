// RadarMind · Dashboard da Subempresa (front-end do grupo, agora conversando com o Laravel)
// Só exibe o resumo da unidade; os cadastros são feitos nas páginas do sistema (links dos botões).
const app = document.getElementById('subempresaApp');
const API = app.dataset.api; // ex.: http://radarmind.test/subempresa/api

const $ = (s) => app.querySelector(s);
const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const data = (d) => new Date(d + 'T00:00:00').toLocaleDateString('pt-BR');

async function api(rota) {
  const resp = await fetch(API + rota, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
  const json = await resp.json().catch(() => ({}));

  if (!resp.ok) {
    throw new Error(json.message || 'Erro inesperado.');
  }

  return json.data ?? json; // listas vêm dentro de "data"
}

function aviso(msg, falha = false) {
  const t = $('#toast');
  t.textContent = msg;
  t.className = 'on' + (falha ? ' falha' : '');
  setTimeout(() => (t.className = ''), 3500);
}

const vazio = (txt) => `<li class="vazio">${txt}</li>`;

function desenhar(dash, itens, os, rep) {
  const k = dash.kpis;
  $('#kpis').innerHTML = [
    ['Itens cadastrados', k.totalItens], ['Itens em alerta', k.itensEmAlerta],
    ['O.S. aguardando análise', k.osAbertas], ['Reposições pendentes', k.reposicoesPendentes],
  ].map(([nome, v]) => `<div class="kpi"><span>${nome}</span><strong>${v}</strong></div>`).join('');

  // Alertas da Central de Notificações (só os da própria unidade)
  const GRAVIDADE = { CRITICO: ['critico', 'crítico'], ALERTA: ['atencao', 'alerta'], CONCLUIDO: ['concluido', 'concluído'] };
  $('#alertas').innerHTML = dash.alertas.length
    ? dash.alertas.map((a) => {
      const [classe, texto] = GRAVIDADE[a.gravidade] ?? GRAVIDADE.ALERTA;
      return `<li class="${classe} ${a.lido ? '' : 'nao-lido'}">${a.lido ? '' : '<b>'}${esc(a.titulo)}${a.lido ? '' : '</b>'}
        <br><small>${esc(a.tempo)}</small> <span class="tag ${classe}">${texto}</span></li>`;
    }).join('')
    : vazio('Nenhum alerta para a sua unidade.');
  $('#alertasNaoLidos').textContent = dash.alertasNaoLidos ? `${dash.alertasNaoLidos} não lido(s)` : '';

  // Pontuação (0 a 100) = movimentações registradas + itens de alto giro em estoque (cada critério vale até pesoPorCriterio)
  const d = dash.desempenho;
  const largura = (pontos) => (pontos / d.pesoPorCriterio) * 100;
  $('#desempenho').innerHTML =
    `<p class="pontuacao"><strong>${d.pontuacao}%</strong> de desempenho</p>
     <div class="barra"><small>Movimentações registradas: ${d.movimentacoes} (${d.pontosMovimentacoes} de ${d.pesoPorCriterio} pts)</small><div style="width:${largura(d.pontosMovimentacoes)}%"></div></div>
     <div class="barra saida"><small>Itens de alto giro em estoque: ${d.itensAltoGiro} (${d.pontosAltoGiro} de ${d.pesoPorCriterio} pts)</small><div style="width:${largura(d.pontosAltoGiro)}%"></div></div>`;

  $('#tbMov').innerHTML = dash.movimentacoesRecentes.length
    ? dash.movimentacoesRecentes.map((m) => `<tr><td>${data(m.data)}</td><td>${esc(m.itemCodigo)} · ${esc(m.itemNome)}</td>
        <td>${esc(m.tipo)}</td><td>${m.quantidade}</td></tr>`).join('')
    : '<tr><td colspan="4" class="vazio">Sem movimentações.</td></tr>';

  $('#tbEstoque').innerHTML = itens.length
    ? itens.map((i) => `<tr><td>${esc(i.codigo)}</td><td>${esc(i.nome)}</td><td>${esc(i.categoria)}</td>
        <td>${i.quantidade}</td><td>${i.minimo}</td><td><span class="tag">${esc(i.status)}</span></td></tr>`).join('')
    : '<tr><td colspan="6" class="vazio">Nenhum item cadastrado.</td></tr>';

  $('#listaOs').innerHTML = os.length
    ? os.map((o) => `<li><b>${esc(o.maquina)}</b> · ${esc(o.prioridade)}<br>${esc(o.tipoProblema)}: ${esc(o.descricao)}
        <span class="tag">${esc(o.status)}</span></li>`).join('')
    : vazio('Nenhuma O.S. registrada.');

  $('#listaRep').innerHTML = rep.length
    ? rep.map((r) => `<li><b>${esc(r.item)}</b> · ${r.quantidade} un.
        <span class="tag">${esc(r.status)}</span></li>`).join('')
    : vazio('Nenhum pedido de reposição.');
}

async function carregar() {
  try {
    const [dash, itens, os, rep] = await Promise.all([api('/dashboard'), api('/estoque'), api('/os'), api('/reposicoes')]);
    desenhar(dash, itens, os, rep);
  } catch (e) {
    aviso('Não foi possível carregar os dados. Verifique a conexão com o banco de dados.', true);
  }
}

carregar();
