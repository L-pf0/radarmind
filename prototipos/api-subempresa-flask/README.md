# 🐍 Protótipo · API da Subempresa (Python + Flask)

Primeira versão da API do módulo da subempresa do RadarMind. Com ela, a equipe validou as regras de negócio e o formato do JSON antes de migrar o módulo para o Laravel. A versão em produção é a do Laravel (`SubempresaController`, rotas `/subempresa/api/*`).

> **Protótipo:** os dados ficam em memória e se perdem quando o servidor reinicia. A subempresa logada é fixa (`SUBEMPRESA_LOGADA = 1`). Não use em produção.

## Endpoints

| Método | Rota | O que faz |
|---|---|---|
| `GET` | `/api/dashboard` | Indicadores, itens em alerta, entradas e saídas dos últimos 30 dias e movimentações recentes |
| `GET` | `/api/estoque` | Lista o estoque da subempresa |
| `POST` | `/api/estoque` | Cadastra um item (código único, quantidade e mínimo inteiros ≥ 0) |
| `GET` | `/api/movimentacoes` | Lista as movimentações com o nome do item |
| `POST` | `/api/movimentacoes` | Registra uma entrada ou saída e bloqueia saída sem saldo |
| `GET` | `/api/os` | Lista as ordens de serviço |
| `POST` | `/api/os` | Abre uma O.S. (somente para maquinário) |
| `GET` | `/api/reposicoes` | Lista os pedidos de reposição |
| `POST` | `/api/reposicoes` | Cria um pedido de reposição |

## Como rodar

Pré-requisito: Python 3.10 ou superior.

```bash
cd prototipos/api-subempresa-flask
python -m venv .venv
```

Ative o ambiente virtual:

```bash
.venv\Scripts\activate
```

> No Linux ou Mac: `source .venv/bin/activate`

Instale as dependências e suba a API:

```bash
pip install -r requirements.txt
python server.py
```

A API sobe em **http://localhost:3000**. Para mudar a porta, defina a variável de ambiente `PORT`.

Teste rápido:

```bash
curl http://localhost:3000/api/dashboard
```
