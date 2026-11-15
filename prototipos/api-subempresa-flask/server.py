# RadarMind - API da Subempresa (dados em memória; trocar por banco depois)
import os
from datetime import datetime, timedelta, timezone

from flask import Flask, g, jsonify, request

app = Flask(__name__)
app.json.ensure_ascii = False   # mantém acentos legíveis no JSON
app.json.sort_keys = False      # mantém a ordem dos campos como definida


# ---------- CORS (o front-end roda em outra porta/arquivo) ----------
@app.after_request
def liberar_cors(resp):
    resp.headers["Access-Control-Allow-Origin"] = "*"
    resp.headers["Access-Control-Allow-Methods"] = "GET, POST, PUT, PATCH, DELETE, OPTIONS"
    resp.headers["Access-Control-Allow-Headers"] = request.headers.get(
        "Access-Control-Request-Headers", "Content-Type"
    )
    return resp


# ---------- "Login" provisório ----------
# Quando o login existir, troque por leitura do token. Todas as consultas
# abaixo filtram por g.sid, garantindo o isolamento entre subempresas.
SUBEMPRESA_LOGADA = 1


@app.before_request
def identificar_subempresa():
    g.sid = SUBEMPRESA_LOGADA


# ---------- Dados de exemplo (substituir pelo banco) ----------
def hoje(d=0):
    """Data (AAAA-MM-DD, em UTC) de hoje menos d dias."""
    return (datetime.now(timezone.utc) - timedelta(days=d)).strftime("%Y-%m-%d")


db = {
    "subempresas": [{"id": 1, "nome": "Subempresa 01"}],
    "estoque": [
        {"id": 1, "sid": 1, "codigo": "PEC-0103", "nome": "Filtro de óleo hidráulico", "categoria": "Peça", "quantidade": 12, "minimo": 10, "status": "Ativo"},
        {"id": 2, "sid": 1, "codigo": "FER-0012", "nome": "Chave de impacto", "categoria": "Ferramenta", "quantidade": 6, "minimo": 4, "status": "Ativo"},
        {"id": 3, "sid": 1, "codigo": "MAQ-0007", "nome": "Pulverizador autopropelido", "categoria": "Maquinário", "quantidade": 1, "minimo": 1, "status": "Ativo"},
        {"id": 4, "sid": 1, "codigo": "PEC-0210", "nome": "Correia dentada", "categoria": "Peça", "quantidade": 3, "minimo": 8, "status": "Ativo"},
    ],
    "movimentacoes": [
        {"id": 1, "sid": 1, "itemId": 1, "tipo": "Entrada", "quantidade": 10, "data": hoje(5)},
        {"id": 2, "sid": 1, "itemId": 2, "tipo": "Saída", "quantidade": 2, "data": hoje(2)},
        {"id": 3, "sid": 1, "itemId": 4, "tipo": "Saída", "quantidade": 5, "data": hoje(1)},
    ],
    "os": [],
    "reposicoes": [],
}
seq = {"estoque": 5, "mov": 4, "os": 1, "rep": 1}


def proximo_id(nome):
    valor = seq[nome]
    seq[nome] += 1
    return valor


# ---------- Utilidades ----------
def erro(msg, status=400):
    return jsonify({"erro": msg}), status


def inteiro(v):
    """Equivale ao Number.isInteger do JavaScript (bool não conta como número)."""
    if isinstance(v, bool):
        return False
    return isinstance(v, int) or (isinstance(v, float) and v.is_integer())


def texto(v):
    """Texto sem espaços nas pontas; vazio se não for string."""
    return v.strip() if isinstance(v, str) else ""


def corpo():
    dados = request.get_json(silent=True)
    return dados if isinstance(dados, dict) else {}


def meus(lista, sid):
    return [x for x in lista if x["sid"] == sid]


def item(sid, id_):
    if not inteiro(id_):
        return None
    return next((i for i in db["estoque"] if i["sid"] == sid and i["id"] == id_), None)


def nivel(i):
    return "crítico" if i["quantidade"] <= i["minimo"] / 2 else "atenção"


def em_alerta(i):
    return i["status"] != "Inativo" and i["quantidade"] <= i["minimo"]


def com_nome(sid, lista):
    resultado = []
    for x in lista:
        it = item(sid, x["itemId"])
        resultado.append({
            **x,
            "itemNome": it["nome"] if it else None,
            "itemCodigo": it["codigo"] if it else None,
        })
    return resultado


# ---------- Dashboard ----------
@app.get("/api/dashboard")
def dashboard():
    sid = g.sid
    est = meus(db["estoque"], sid)
    movs = meus(db["movimentacoes"], sid)
    limite = hoje(30)

    def soma(tipo):
        return sum(m["quantidade"] for m in movs if m["tipo"] == tipo and m["data"] >= limite)

    alertas = [{**i, "nivel": nivel(i)} for i in est if em_alerta(i)]
    recentes = sorted(movs, key=lambda m: m["id"], reverse=True)[:6]
    return jsonify({
        "subempresa": next((s for s in db["subempresas"] if s["id"] == sid), None),
        "kpis": {
            "totalItens": len(est),
            "itensEmAlerta": len(alertas),
            "osAbertas": len([o for o in meus(db["os"], sid) if o["status"] == "Aguardando análise"]),
            "reposicoesPendentes": len([r for r in meus(db["reposicoes"], sid) if r["status"] == "Pendente"]),
        },
        "desempenho": {"entradas30d": soma("Entrada"), "saidas30d": soma("Saída")},
        "alertas": alertas,
        "movimentacoesRecentes": com_nome(sid, recentes),
    })


# ---------- Estoque ----------
@app.get("/api/estoque")
def listar_estoque():
    return jsonify(meus(db["estoque"], g.sid))


@app.post("/api/estoque")
def cadastrar_estoque():
    d = corpo()
    codigo, nome, categoria = texto(d.get("codigo")), texto(d.get("nome")), d.get("categoria")
    quantidade, minimo, status = d.get("quantidade"), d.get("minimo"), d.get("status")
    if not codigo or not nome or not categoria:
        return erro("Preencha código, nome e categoria.")
    if not inteiro(quantidade) or quantidade < 0:
        return erro("Quantidade deve ser um número inteiro maior ou igual a zero.")
    if not inteiro(minimo) or minimo < 0:
        return erro("Estoque mínimo deve ser um número inteiro maior ou igual a zero.")
    cod = codigo.upper()
    if any(i["codigo"] == cod for i in meus(db["estoque"], g.sid)):
        return erro(f"O código {cod} já está cadastrado.", 409)
    novo = {
        "id": proximo_id("estoque"), "sid": g.sid, "codigo": cod, "nome": nome,
        "categoria": categoria, "quantidade": int(quantidade), "minimo": int(minimo),
        "status": status or "Ativo",
    }
    db["estoque"].append(novo)
    return jsonify(novo), 201


# ---------- Movimentações ----------
@app.get("/api/movimentacoes")
def listar_movimentacoes():
    return jsonify(com_nome(g.sid, meus(db["movimentacoes"], g.sid)))


@app.post("/api/movimentacoes")
def cadastrar_movimentacao():
    d = corpo()
    item_id, tipo, quantidade, data = d.get("itemId"), d.get("tipo"), d.get("quantidade"), d.get("data")
    it = item(g.sid, item_id)
    if not it:
        return erro("Selecione um item válido.")
    if tipo not in ("Entrada", "Saída"):
        return erro("Tipo deve ser Entrada ou Saída.")
    if not inteiro(quantidade) or quantidade <= 0:
        return erro("Quantidade deve ser maior que zero.")
    quantidade = int(quantidade)
    if tipo == "Saída" and quantidade > it["quantidade"]:
        return erro(f"Saldo insuficiente: o item tem {it['quantidade']} unidade(s).")
    it["quantidade"] += quantidade if tipo == "Entrada" else -quantidade
    mov = {"id": proximo_id("mov"), "sid": g.sid, "itemId": int(item_id), "tipo": tipo,
           "quantidade": quantidade, "data": data or hoje()}
    db["movimentacoes"].append(mov)
    return jsonify({"movimentacao": mov, "saldoAtual": it["quantidade"], "alerta": em_alerta(it)}), 201


# ---------- Ordens de serviço (a Matriz é quem altera o status) ----------
@app.get("/api/os")
def listar_os():
    return jsonify(com_nome(g.sid, meus(db["os"], g.sid)))


@app.post("/api/os")
def registrar_os():
    d = corpo()
    item_id, descricao, prioridade = d.get("itemId"), texto(d.get("descricao")), d.get("prioridade")
    it = item(g.sid, item_id)
    if not it or it["categoria"] != "Maquinário":
        return erro("Selecione o maquinário com defeito.")
    if not descricao:
        return erro("Descreva o defeito.")
    os_ = {"id": proximo_id("os"), "sid": g.sid, "itemId": int(item_id), "descricao": descricao,
           "prioridade": prioridade or "Média", "status": "Aguardando análise", "data": hoje()}
    db["os"].append(os_)
    return jsonify(os_), 201


# ---------- Pedidos de reposição ----------
@app.get("/api/reposicoes")
def listar_reposicoes():
    return jsonify(com_nome(g.sid, meus(db["reposicoes"], g.sid)))


@app.post("/api/reposicoes")
def registrar_reposicao():
    d = corpo()
    item_id, quantidade, observacao = d.get("itemId"), d.get("quantidade"), d.get("observacao")
    if not item(g.sid, item_id):
        return erro("Selecione um item válido.")
    if not inteiro(quantidade) or quantidade <= 0:
        return erro("Quantidade deve ser maior que zero.")
    rep = {"id": proximo_id("rep"), "sid": g.sid, "itemId": int(item_id), "quantidade": int(quantidade),
           "observacao": texto(observacao), "status": "Pendente", "data": hoje()}
    db["reposicoes"].append(rep)
    return jsonify(rep), 201


if __name__ == "__main__":
    PORT = int(os.environ.get("PORT", 3000))
    print(f"RadarMind API rodando em http://localhost:{PORT}")
    app.run(host="0.0.0.0", port=PORT)
