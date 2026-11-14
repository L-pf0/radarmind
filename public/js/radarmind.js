const toast=(msg)=>{let t=document.querySelector('.toast');if(!t){t=document.createElement('div');t.className='toast';document.body.appendChild(t)}t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2200)};
document.querySelectorAll('[data-toggle]').forEach(b=>b.addEventListener('click',()=>document.getElementById(b.dataset.toggle)?.classList.toggle('hidden')));
const catSel=document.getElementById('cat-sel');if(catSel){const apply=()=>document.querySelectorAll('[data-for]').forEach(el=>el.classList.toggle('hidden',el.dataset.for!==catSel.value));catSel.addEventListener('change',apply);apply()}
document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('submit',e=>{if(!confirm(el.dataset.confirm))e.preventDefault()}));
// Emissão de relatórios: escolhe o tipo pelo card e mostra o período só para "Movimentações"
const tipoDoRelatorio = document.getElementById('report-type');
if (tipoDoRelatorio) {
    const aplicarTipo = () => {
        document.querySelectorAll('.pick[data-report]').forEach(card => {
            const escolhido = card.dataset.report === tipoDoRelatorio.value;
            card.classList.toggle('selected', escolhido);
            card.setAttribute('aria-pressed', escolhido ? 'true' : 'false');
        });
        document.querySelectorAll('[data-somente-para]').forEach(bloco => {
            const mostrar = bloco.dataset.somentePara === tipoDoRelatorio.value;
            bloco.classList.toggle('hidden', !mostrar);
            // campo escondido não vai junto no envio
            bloco.querySelectorAll('input, select').forEach(campo => { campo.disabled = !mostrar; });
        });
    };
    document.querySelectorAll('.pick[data-report]').forEach(card => card.addEventListener('click', () => {
        tipoDoRelatorio.value = card.dataset.report;
        aplicarTipo();
    }));
    aplicarTipo();
}
document.querySelectorAll('[data-toast]').forEach(el=>el.addEventListener('click',()=>toast(el.dataset.toast)));

// Links dos dashboards (ex.: /estoque#form-item): abre o formulário escondido e rola até ele
if (location.hash) {
    const alvo = document.getElementById(location.hash.slice(1));
    if (alvo) {
        alvo.classList.remove('hidden');
        alvo.scrollIntoView({ behavior: 'smooth', block: 'start' });
        alvo.querySelector('input:not([type="hidden"]):not([disabled]), select, textarea')?.focus({ preventScroll: true });
    }
}

// Tabelas responsivas: copia o texto do cabeçalho para o data-label de cada célula.
// No celular o CSS (.rm-stack) transforma cada linha em um cartão "COLUNA ...... valor".
document.querySelectorAll('.rm-modulo table').forEach(tabela => {
    const titulos = [...tabela.querySelectorAll('thead th')].map(th => th.textContent.trim());
    if (!titulos.length) return;
    tabela.querySelectorAll('tbody tr').forEach(linha => {
        [...linha.children].forEach((celula, i) => {
            if (!celula.hasAttribute('colspan')) celula.dataset.label = titulos[i] ?? '';
        });
    });
    tabela.classList.add('rm-stack');
});
