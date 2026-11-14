document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("formOS");
  if (!form) return;

  const descricao = document.getElementById("descricao");
  const contador = document.getElementById("contador");
  const btnEnviar = document.getElementById("btnEnviar");
  const btnLimpar = document.getElementById("btnLimpar");

  const mensagens = {
    solicitante: "Informe o nome do solicitante.",
    maquina: "Informe a máquina ou peça.",
    tipo_problema: "Informe o tipo de problema.",
    prioridade: "Selecione o nível de prioridade.",
    descricao: "Descreva o defeito (mínimo de 10 caracteres)."
  };
  const nomes = Object.keys(mensagens);

  const erroDe = (nome) => document.querySelector(`[data-erro-for="${nome}"]`);

  const atualizarContador = () => {
    contador.textContent = `${descricao.value.length}/${descricao.maxLength}`;
  };
  descricao.addEventListener("input", atualizarContador);
  atualizarContador();

  function validar(nome) {
    let valido;
    let alvo;

    if (nome === "prioridade") {
      valido = !!form.querySelector('input[name="prioridade"]:checked');
      alvo = document.getElementById("prioridade");
    } else {
      const campo = form.elements[nome];
      const valor = campo.value.trim();
      valido = nome === "descricao" ? valor.length >= 10 : valor !== "";
      alvo = campo.closest(".input-group") || campo;
    }

    alvo.classList.toggle("is-invalid", !valido);
    erroDe(nome).textContent = valido ? "" : mensagens[nome];
    return valido;
  }

  // Revalida enquanto a pessoa corrige um campo que estava com erro
  nomes.forEach((nome) => {
    form.querySelectorAll(`[name="${nome}"]`).forEach((campo) => {
      ["input", "change"].forEach((evento) => {
        campo.addEventListener(evento, () => {
          if (erroDe(nome).textContent) validar(nome);
        });
      });
    });
  });

  // "Limpar" esvazia tudo de verdade (reset voltaria aos valores antigos do servidor)
  btnLimpar.addEventListener("click", () => {
    form.querySelectorAll('input[type="text"], textarea').forEach((el) => (el.value = ""));
    form.querySelectorAll('input[type="radio"]').forEach((el) => (el.checked = false));
    form.querySelectorAll(".is-invalid").forEach((el) => el.classList.remove("is-invalid"));
    form.querySelectorAll(".rm-field-error").forEach((el) => (el.textContent = ""));
    atualizarContador();
    document.getElementById("solicitante").focus();
  });

  form.addEventListener("submit", (e) => {
    let primeiro = null;
    nomes.forEach((nome) => {
      if (!validar(nome) && !primeiro) primeiro = nome;
    });

    if (primeiro) {
      e.preventDefault();
      const foco = primeiro === "prioridade"
        ? form.querySelector('input[name="prioridade"]')
        : form.elements[primeiro];
      foco.focus();
      return;
    }

    btnEnviar.disabled = true; // evita clique duplo
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("formOS");
  if (!form) return;

  const descricao = document.getElementById("descricao");
  const contador = document.getElementById("contador");
  const btnEnviar = document.getElementById("btnEnviar");
  const btnLimpar = document.getElementById("btnLimpar");

  const mensagens = {
    solicitante: "Informe o nome do solicitante.",
    maquina: "Informe a máquina ou peça.",
    tipo_problema: "Informe o tipo de problema.",
    prioridade: "Selecione o nível de prioridade.",
    descricao: "Descreva o defeito (mínimo de 10 caracteres)."
  };
  const nomes = Object.keys(mensagens);

  const erroDe = (nome) => document.querySelector(`[data-erro-for="${nome}"]`);

  const atualizarContador = () => {
    contador.textContent = `${descricao.value.length}/${descricao.maxLength}`;
  };
  descricao.addEventListener("input", atualizarContador);
  atualizarContador();

  function validar(nome) {
    let valido;
    let alvo;

    if (nome === "prioridade") {
      valido = !!form.querySelector('input[name="prioridade"]:checked');
      alvo = document.getElementById("prioridade");
    } else {
      const campo = form.elements[nome];
      const valor = campo.value.trim();
      valido = nome === "descricao" ? valor.length >= 10 : valor !== "";
      alvo = campo.closest(".input-group") || campo;
    }

    alvo.classList.toggle("is-invalid", !valido);
    erroDe(nome).textContent = valido ? "" : mensagens[nome];
    return valido;
  }

  // Revalida enquanto a pessoa corrige um campo que estava com erro
  nomes.forEach((nome) => {
    form.querySelectorAll(`[name="${nome}"]`).forEach((campo) => {
      ["input", "change"].forEach((evento) => {
        campo.addEventListener(evento, () => {
          if (erroDe(nome).textContent) validar(nome);
        });
      });
    });
  });

  // "Limpar" esvazia tudo de verdade (reset voltaria aos valores antigos do servidor)
  btnLimpar.addEventListener("click", () => {
    form.querySelectorAll('input[type="text"], textarea').forEach((el) => (el.value = ""));
    form.querySelectorAll('input[type="radio"]').forEach((el) => (el.checked = false));
    form.querySelectorAll(".is-invalid").forEach((el) => el.classList.remove("is-invalid"));
    form.querySelectorAll(".rm-field-error").forEach((el) => (el.textContent = ""));
    atualizarContador();
    document.getElementById("solicitante").focus();
  });

  form.addEventListener("submit", (e) => {
    let primeiro = null;
    nomes.forEach((nome) => {
      if (!validar(nome) && !primeiro) primeiro = nome;
    });

    if (primeiro) {
      e.preventDefault();
      const foco = primeiro === "prioridade"
        ? form.querySelector('input[name="prioridade"]')
        : form.elements[primeiro];
      foco.focus();
      return;
    }

    btnEnviar.disabled = true; // evita clique duplo
  });
});

/* ==========================================================================
   INDEX - filtro dos cartões por status
   ========================================================================== */
document.addEventListener("DOMContentLoaded", () => {
  const filtros = document.querySelectorAll("[data-filtro]");
  if (!filtros.length) return;

  const cartoes = document.querySelectorAll(".rm-os-card");
  const semResultado = document.getElementById("osSemResultado");

  filtros.forEach((botao) => {
    botao.addEventListener("click", () => {
      const alvo = botao.dataset.filtro;
      let visiveis = 0;

      filtros.forEach((b) => b.classList.toggle("active", b === botao));

      cartoes.forEach((cartao) => {
        const mostrar = alvo === "todas" || cartao.dataset.status === alvo;
        cartao.classList.toggle("d-none", !mostrar);
        if (mostrar) visiveis++;
      });

      if (semResultado) semResultado.classList.toggle("d-none", visiveis > 0);
    });
  });
});



document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("formOS");
  if (!form) return;

  const descricao = document.getElementById("descricao");
  const contador = document.getElementById("contador");
  const btnEnviar = document.getElementById("btnEnviar");
  const btnLimpar = document.getElementById("btnLimpar");

  const mensagens = {
    solicitante: "Informe o nome do solicitante.",
    maquina: "Informe a máquina ou peça.",
    tipo_problema: "Informe o tipo de problema.",
    prioridade: "Selecione o nível de prioridade.",
    descricao: "Descreva o defeito (mínimo de 10 caracteres)."
  };
  const nomes = Object.keys(mensagens);

  const erroDe = (nome) => document.querySelector(`[data-erro-for="${nome}"]`);

  const atualizarContador = () => {
    contador.textContent = `${descricao.value.length}/${descricao.maxLength}`;
  };
  descricao.addEventListener("input", atualizarContador);
  atualizarContador();

  function validar(nome) {
    let valido;
    let alvo;

    if (nome === "prioridade") {
      valido = !!form.querySelector('input[name="prioridade"]:checked');
      alvo = document.getElementById("prioridade");
    } else {
      const campo = form.elements[nome];
      const valor = campo.value.trim();
      valido = nome === "descricao" ? valor.length >= 10 : valor !== "";
      alvo = campo.closest(".input-group") || campo;
    }

    alvo.classList.toggle("is-invalid", !valido);
    erroDe(nome).textContent = valido ? "" : mensagens[nome];
    return valido;
  }

  // Revalida enquanto a pessoa corrige um campo que estava com erro
  nomes.forEach((nome) => {
    form.querySelectorAll(`[name="${nome}"]`).forEach((campo) => {
      ["input", "change"].forEach((evento) => {
        campo.addEventListener(evento, () => {
          if (erroDe(nome).textContent) validar(nome);
        });
      });
    });
  });

  // "Limpar" esvazia tudo de verdade (reset voltaria aos valores antigos do servidor)
  btnLimpar.addEventListener("click", () => {
    form.querySelectorAll('input[type="text"], textarea').forEach((el) => (el.value = ""));
    form.querySelectorAll('input[type="radio"]').forEach((el) => (el.checked = false));
    form.querySelectorAll(".is-invalid").forEach((el) => el.classList.remove("is-invalid"));
    form.querySelectorAll(".rm-field-error").forEach((el) => (el.textContent = ""));
    atualizarContador();
    document.getElementById("solicitante").focus();
  });

  form.addEventListener("submit", (e) => {
    let primeiro = null;
    nomes.forEach((nome) => {
      if (!validar(nome) && !primeiro) primeiro = nome;
    });

    if (primeiro) {
      e.preventDefault();
      const foco = primeiro === "prioridade"
        ? form.querySelector('input[name="prioridade"]')
        : form.elements[primeiro];
      foco.focus();
      return;
    }

    btnEnviar.disabled = true; // evita clique duplo
  });
});

/* ==========================================================================
   INDEX - filtro dos cartões por status
   ========================================================================== */
document.addEventListener("DOMContentLoaded", () => {
  const filtros = document.querySelectorAll("[data-filtro]");
  if (!filtros.length) return;

  const cartoes = document.querySelectorAll(".rm-os-card");
  const semResultado = document.getElementById("osSemResultado");

  filtros.forEach((botao) => {
    botao.addEventListener("click", () => {
      const alvo = botao.dataset.filtro;
      let visiveis = 0;

      filtros.forEach((b) => b.classList.toggle("active", b === botao));

      cartoes.forEach((cartao) => {
        const mostrar = alvo === "todas" || cartao.dataset.status === alvo;
        cartao.classList.toggle("d-none", !mostrar);
        if (mostrar) visiveis++;
      });

      if (semResultado) semResultado.classList.toggle("d-none", visiveis > 0);
    });
  });
});

/* ==========================================================================
   EDIT - só habilita "Salvar" quando o status foi trocado
   ========================================================================== */
document.addEventListener("DOMContentLoaded", () => {
  const formStatus = document.getElementById("formStatus");
  if (!formStatus) return;

  const botao = document.getElementById("btnSalvarStatus");
  const original = formStatus.dataset.original;

  const atualizar = () => {
    const marcado = formStatus.querySelector('input[name="status"]:checked');
    botao.disabled = !marcado || marcado.value === original;
  };

  formStatus.addEventListener("change", atualizar);
  formStatus.addEventListener("submit", () => {
    botao.disabled = true; // evita clique duplo
  });
  atualizar();
});