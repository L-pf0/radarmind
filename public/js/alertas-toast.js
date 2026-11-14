/*
 * Notificação de novos alertas (canto inferior esquerdo).
 * Consulta /alertas/novos de tempos em tempos; cada alerta novo vira um cartão com a
 * criticidade, uma descrição curta e "Clique para ver", que leva para a Central de Alertas.
 * O servidor guarda na sessão o último alerta já notificado, então nada aparece repetido.
 */
(function () {
    "use strict";

    const container = document.getElementById("rm-toasts");
    if (!container) {
        return;
    }

    const URL_NOVOS = container.dataset.urlNovos;
    const URL_CENTRAL = container.dataset.urlCentral;
    const INTERVALO_MS = 15000;
    const DURACAO_MS = 12000;
    const MAXIMO_NA_TELA = 4;

    const GRAVIDADES = {
        CRITICO: { classe: "critico", rotulo: "Crítico", icone: "fa-solid fa-circle-exclamation" },
        ALERTA: { classe: "alerta", rotulo: "Alerta", icone: "fa-solid fa-triangle-exclamation" },
        CONCLUIDO: { classe: "concluido", rotulo: "Concluído", icone: "fa-solid fa-circle-check" },
    };

    let consultando = false;

    function elemento(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) {
            el.className = classe;
        }
        if (texto !== undefined) {
            el.textContent = texto;
        }
        return el;
    }

    function quando(dataIso) {
        const minutos = Math.floor((Date.now() - new Date(dataIso).getTime()) / 60000);
        if (!Number.isFinite(minutos) || minutos < 1) {
            return "agora";
        }
        return minutos < 60 ? `há ${minutos} min` : `há ${Math.floor(minutos / 60)}h`;
    }

    /** Descrição curta: item + quantidade (ou a mensagem do alerta quando não há item). */
    function descricao(alerta) {
        const d = alerta.detalhes || {};
        const item = [d.item_codigo, d.item_nome].filter(Boolean).join(" · ");
        let quantidade = "";

        if (d.qtd_recebida) {
            quantidade = `+${d.qtd_recebida} un. recebidas`;
        } else if (d.qtd_atual !== null && d.qtd_atual !== undefined && d.qtd_minima !== null && d.qtd_minima !== undefined) {
            quantidade = `${d.qtd_atual} un. (mín. ${d.qtd_minima})`;
        }

        if (!item) {
            return (d.mensagem || "").replace(/:\s*$/, "");
        }
        return quantidade ? `${item} — ${quantidade}` : item;
    }

    function fechar(toast) {
        if (toast.classList.contains("saindo")) {
            return;
        }
        clearTimeout(toast._timer);
        toast.classList.add("saindo");
        toast.addEventListener("animationend", () => toast.remove(), { once: true });
        // sem animação (movimento reduzido), remove na hora
        setTimeout(() => toast.remove(), 400);
    }

    /** Some sozinho depois de DURACAO_MS; o tempo para enquanto o mouse ou o foco está no cartão. */
    function agendarSaida(toast) {
        let restante = DURACAO_MS;
        let inicio = Date.now();

        const iniciar = () => {
            inicio = Date.now();
            toast._timer = setTimeout(() => fechar(toast), restante);
        };
        const pausar = () => {
            clearTimeout(toast._timer);
            restante -= Date.now() - inicio;
        };

        toast.style.setProperty("--toast-duracao", `${DURACAO_MS}ms`);
        toast.addEventListener("mouseenter", pausar);
        toast.addEventListener("mouseleave", iniciar);
        toast.addEventListener("focusin", pausar);
        toast.addEventListener("focusout", iniciar);
        iniciar();
    }

    function montarToast({ classe, rotulo, icone, titulo, texto, momento }) {
        const toast = elemento("div", `rm-toast ${classe}`);
        toast.setAttribute("role", "status");

        const link = elemento("a", "rm-toast-link");
        link.href = URL_CENTRAL;

        const iconeEl = elemento("span", "rm-toast-icone");
        iconeEl.setAttribute("aria-hidden", "true");
        iconeEl.appendChild(elemento("i", icone));

        const corpo = elemento("div", "rm-toast-corpo");
        const topo = elemento("div", "rm-toast-topo");
        topo.appendChild(elemento("span", "rm-toast-gravidade", rotulo));
        if (momento) {
            topo.appendChild(elemento("span", "rm-toast-quando", momento));
        }
        corpo.appendChild(topo);
        corpo.appendChild(elemento("p", "rm-toast-titulo", titulo));
        if (texto) {
            corpo.appendChild(elemento("p", "rm-toast-descricao", texto));
        }

        const ver = elemento("span", "rm-toast-ver", "Clique para ver ");
        const seta = elemento("i", "fa-solid fa-arrow-right");
        seta.setAttribute("aria-hidden", "true");
        ver.appendChild(seta);
        corpo.appendChild(ver);

        link.append(iconeEl, corpo);

        const botaoFechar = elemento("button", "rm-toast-fechar");
        botaoFechar.type = "button";
        botaoFechar.setAttribute("aria-label", "Fechar notificação");
        botaoFechar.appendChild(elemento("i", "fa-solid fa-xmark"));
        botaoFechar.addEventListener("click", () => fechar(toast));

        toast.append(link, botaoFechar, elemento("span", "rm-toast-tempo"));

        container.appendChild(toast);
        agendarSaida(toast);

        // não deixa a pilha crescer: tira os mais antigos
        const naTela = container.querySelectorAll(".rm-toast:not(.saindo)");
        for (let i = 0; i < naTela.length - MAXIMO_NA_TELA; i++) {
            fechar(naTela[i]);
        }
    }

    function mostrarAlerta(alerta) {
        const gravidade = GRAVIDADES[alerta.gravidade] || GRAVIDADES.ALERTA;
        montarToast({
            classe: gravidade.classe,
            rotulo: gravidade.rotulo,
            icone: gravidade.icone,
            titulo: alerta.titulo,
            texto: descricao(alerta),
            momento: quando(alerta.data_criacao),
        });
    }

    async function consultar() {
        if (consultando || document.visibilityState === "hidden") {
            return;
        }
        consultando = true;

        try {
            const resposta = await fetch(URL_NOVOS, {
                headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                credentials: "same-origin",
            });
            if (!resposta.ok) {
                return;
            }

            const json = await resposta.json();
            const alertas = Array.isArray(json.data) ? json.data : [];
            const restantes = (json.total || 0) - alertas.length;

            if (restantes > 0) {
                montarToast({
                    classe: "resumo",
                    rotulo: "Novos alertas",
                    icone: "fa-solid fa-bell",
                    titulo: `Mais ${restantes} alerta(s) novo(s) na central`,
                    texto: "",
                    momento: "",
                });
            }

            // do mais antigo para o mais novo: o mais recente fica por cima da pilha
            alertas.slice().reverse().forEach(mostrarAlerta);
        } catch (erro) {
            // sem conexão: tenta de novo na próxima rodada
        } finally {
            consultando = false;
        }
    }

    consultar();
    setInterval(consultar, INTERVALO_MS);
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") {
            consultar();
        }
    });
})();
