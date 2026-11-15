<p align="center">
  <img src="public/images/logoCortada_RadarMind.png" alt="Logo do RadarMind" width="180">
</p>

<h1 align="center">RadarMind</h1>

<p align="center">
  <strong>Gestão inteligente e integrada para um futuro mais eficiente.</strong><br>
  Plataforma web para a matriz e as subempresas do agronegócio controlarem estoque, maquinário,<br>
  ordens de serviço, pedidos de reposição e alertas em um só lugar.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Python-3.12-3776AB?logo=python&logoColor=white" alt="Python 3.12">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/testes-Pest-1F8A4C" alt="Testes com Pest">
  <img src="https://img.shields.io/badge/status-online-2EA44F" alt="Status online">
</p>

<p align="center">
  <a href="http://radarmindgestaointeligente.infinityfreeapp.com"><strong>🌐 Acessar a demonstração online</strong></a>
</p>

---

## 📑 Sumário

- [Sobre o projeto](#-sobre-o-projeto)
- [Funcionalidades](#-funcionalidades)
- [Perfis de acesso](#-perfis-de-acesso)
- [Fluxos principais](#-fluxos-principais)
- [Tecnologias](#-tecnologias)
- [Como rodar localmente](#-como-rodar-localmente)
- [Contas de demonstração](#-contas-de-demonstração)
- [Testes](#-testes)
- [Estrutura do projeto](#-estrutura-do-projeto)
- [Publicação em produção](#-publicação-em-produção)
- [Segurança](#-segurança)
- [Equipe](#-equipe)

---

## 💡 Sobre o projeto

Empresas do agronegócio com várias unidades costumam controlar peças, máquinas e manutenções em planilhas separadas. Por isso a matriz demora a saber o que falta em cada filial, e as filiais não sabem em que pé está cada pedido.

O **RadarMind** junta tudo em uma única plataforma:

- **A matriz** vê todas as unidades, aprova pedidos, autoriza ordens de serviço e gera relatórios consolidados.
- **Cada subempresa** cuida do próprio estoque, registra movimentações, abre O.S. e solicita reposições.
- **Os alertas automáticos** avisam as duas pontas na hora: estoque crítico, pedido pendente, máquina parada, O.S. finalizada.

## ✨ Funcionalidades

| Módulo | O que faz |
|---|---|
| 📊 **Dashboards** | Painel da matriz com o ranking de desempenho das unidades e painel próprio de cada subempresa com os indicadores da unidade. |
| 📦 **Estoque** | Cadastro de **peças avulsas** e **maquinário** com SKU, localização, lote, número de série, status (Ativo, Em manutenção, Inativo) e giro. Campos obrigatórios sinalizados e validados. |
| 🔄 **Movimentações** | Entradas e saídas com responsável e histórico. O estoque é atualizado automaticamente. |
| 🛒 **Pedidos de reposição** | A subempresa solicita, a matriz é avisada e a própria subempresa confirma a chegada. O estoque é somado automaticamente, uma única vez (Em aberto → Atendido / Cancelado). |
| 🛠️ **Ordens de serviço** | A subempresa abre a O.S. e a matriz autoriza, nega ou marca como **Feita**. As concluídas vão para o quadro **Finalizadas**. |
| 🔔 **Central de alertas** | Alertas por gravidade (**Crítico**, **Alerta**, **Concluído**) e categoria (Estoque, Pedidos, Maquinário, Manutenção), cada um com o botão da próxima ação. |
| 💬 **Notificações em tempo real** | Aviso no canto da tela sempre que chega um alerta novo, com link direto para a central. |
| 📄 **Relatórios** | Estoque, movimentações e desempenho, com pré-visualização, **impressão em PDF** e **exportação CSV** pronta para o Excel. |
| 🏆 **Medidor de desempenho** | Pontuação de 0 a 100 por unidade: 50% pelo volume de movimentações e 50% pelos itens de alto giro. |

## 👥 Perfis de acesso

<table>
  <tr>
    <th width="50%">🏢 Matriz</th>
    <th width="50%">🏭 Subempresas</th>
  </tr>
  <tr>
    <td valign="top">
      <ul>
        <li>Visualiza todas as unidades</li>
        <li>Relatórios consolidados ou por unidade</li>
        <li>Acompanha os pedidos de reposição de toda a rede</li>
        <li>Autoriza, nega e finaliza O.S.</li>
        <li>Recebe os alertas de toda a rede</li>
      </ul>
    </td>
    <td valign="top">
      <ul>
        <li>Gerencia apenas a própria unidade</li>
        <li>Controle de estoque e movimentações</li>
        <li>Solicita reposição e confirma a chegada</li>
        <li>Abre e acompanha O.S.</li>
        <li>Recebe os alertas da sua unidade</li>
      </ul>
    </td>
  </tr>
</table>

O acesso é protegido por perfil: uma subempresa nunca enxerga nem altera os dados de outra unidade.

## 🔁 Fluxos principais

### Pedido de reposição

```mermaid
flowchart LR
    A[Estoque abaixo do mínimo] -->|alerta automático| B[Subempresa solicita reposição]
    B --> C[Em aberto]
    C -->|alerta para a matriz| D{Reposição chegou?}
    D -->|sim: subempresa confirma e informa quem recebeu| E[Atendido ✔]
    D -->|não será atendido| X[Cancelado]
    E --> F[Estoque somado e entrada registrada nas movimentações]
```

### Ordem de serviço

```mermaid
flowchart LR
    A[Máquina com problema] --> B[Subempresa abre a O.S.]
    B --> C[Aguardando autorização]
    C -->|matriz nega| N[Negada]
    C -->|matriz autoriza| D[Autorizada]
    D -->|serviço concluído| E[Feita ✔]
    E -->|alerta para a unidade| F[Quadro Finalizadas]
```

## 🧰 Tecnologias

- **Back-end:** PHP 8.4, Laravel 13 (Eloquent, Observers, Form Requests, API Resources, Middleware)
- **Banco de dados:** MySQL / MariaDB
- **Front-end:** Blade, CSS próprio e JavaScript puro (sem build obrigatório)
- **Testes:** Pest
- **Padrão de código:** Laravel Pint

## 🚀 Como rodar localmente

### Pré-requisitos

- PHP **8.3 ou superior** com as extensões `pdo_mysql`, `mbstring`, `openssl` e `fileinfo`
- [Composer](https://getcomposer.org/)
- MySQL 8 (ou MariaDB)
- Opcional: [Laravel Herd](https://herd.laravel.com/) ou XAMPP para servir o projeto

### Passo a passo

**1. Clone o repositório**

```bash
git clone https://github.com/L-pf0/radarmind.git
cd radarmind
```

**2. Instale as dependências**

```bash
composer install
```

**3. Crie o arquivo de ambiente e gere a chave da aplicação**

```bash
cp .env.example .env
php artisan key:generate
```

> No Windows (PowerShell), use `copy .env.example .env`.

**4. Configure o banco no `.env`**

Crie um banco vazio (por exemplo `radarmind`) e preencha:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=radarmind
DB_USERNAME=root
DB_PASSWORD=
```

**5. Defina as senhas das contas de demonstração** (opcional)

```env
SEED_SENHA_MATRIZ=
SEED_SENHA_SUB01=
SEED_SENHA_SUB02=
SEED_SENHA_SUB03=
SEED_SENHA_SUB04=
```

> Se ficarem em branco, a seed gera senhas aleatórias e mostra cada uma no terminal.

**6. Crie as tabelas e os dados iniciais**

```bash
php artisan migrate --seed
```

**7. Inicie o servidor**

```bash
php artisan serve
```

Acesse **http://localhost:8000**. No Laravel Herd, o projeto já fica disponível em `http://radarmind.test`.

## 🔑 Contas de demonstração

A seed cria a matriz, quatro subempresas e um estoque realista para cada uma (**353 itens** no total, entre peças e maquinário, além dos alertas iniciais).

| Perfil | E-mail | Unidade | Perfil do estoque |
|---|---|---|---|
| Matriz | `matriz@gmail.com` | MAT | Peças e frota completas |
| Subempresa | `subempresa01@gmail.com` | SUB01 | Operação geral |
| Subempresa | `subempresa02@gmail.com` | SUB02 | Pulverização |
| Subempresa | `subempresa03@gmail.com` | SUB03 | Solo e plantio |
| Subempresa | `subempresa04@gmail.com` | SUB04 | Colheita |

As senhas **não ficam no código**. Elas vêm das variáveis `SEED_SENHA_*` do seu `.env`.

## 🧪 Testes

O projeto tem uma suíte de testes automatizados com Pest. Ela cobre login e limite de tentativas, permissões por perfil, estoque, O.S., pedidos, alertas, notificações, relatórios (tela e CSV) e as seeds.

```bash
php artisan test
```

Para rodar só um arquivo:

```bash
php artisan test tests/Feature/RelatoriosTest.php
```

## 🗂️ Estrutura do projeto

```text
app/
├── Http/
│   ├── Controllers/        # Login, dashboards, alertas, O.S., pedidos e módulo RadarMind
│   ├── Middleware/         # GarantirPerfil: controle de acesso matriz / subempresa
│   ├── Requests/           # Validação de formulários (relatórios, pedidos)
│   └── Resources/          # Respostas JSON das telas dinâmicas
├── Models/                 # Itens, unidades, movimentações, O.S., pedidos e alertas
├── Observers/              # Geram alertas automaticamente quando algo muda
└── Support/
    ├── GeradorDeAlertas.php
    ├── MedidorDeDesempenho.php
    └── Relatorios/         # Montagem dos relatórios e exportação CSV
config/radarmind.php        # Layout e contas criadas pela seed
database/
├── migrations/
└── seeders/                # Unidades, usuários e estoque de demonstração
public/                     # CSS, JavaScript e imagens
resources/views/            # Telas em Blade
routes/web.php              # Rotas da aplicação
tests/                      # Testes automatizados (Pest)
```

## 🌍 Publicação em produção

O projeto roda em hospedagens PHP comuns, inclusive gratuitas. Resumo:

1. Rode `composer install --no-dev --optimize-autoloader`.
2. Crie o `.env` de produção a partir do [`.env.production.example`](.env.production.example), com `APP_ENV=production`, `APP_DEBUG=false` e uma `APP_KEY` nova.
3. Aponte o domínio para a pasta `public/`. Se a hospedagem não permitir, use um `.htaccess` na raiz que encaminhe tudo para `public/`.
4. Crie as tabelas com `php artisan migrate --seed` ou importe um dump SQL pelo phpMyAdmin.
5. Depois de ativar o SSL, use `APP_URL=https://...` e `SESSION_SECURE_COOKIE=true`.

## 🔒 Segurança

- Senhas guardadas com hash (bcrypt). As contas de demonstração leem a senha do `.env`, nunca do código.
- Limite de **5 tentativas de login por minuto** por e-mail e IP.
- Isolamento de dados por unidade com o middleware de perfil e escopos nos modelos.
- Proteção CSRF em todos os formulários e sessão criptografada em produção.
- Exportação CSV protegida contra injeção de fórmulas no Excel.
- O `.env`, a pasta `vendor` e os logs ficam fora do repositório (`.gitignore`).

## 👨‍💻 Equipe

| Integrante | Responsabilidade |
|---|---|
| _Nome do integrante_ | _Ex.: módulo de estoque_ |
| _Nome do integrante_ | _Ex.: alertas e notificações_ |
| _Nome do integrante_ | _Ex.: relatórios_ |
| _Nome do integrante_ | _Ex.: interface e design_ |

---

<p align="center">
  Feito com 💚 para tornar a gestão do agronegócio mais simples e eficiente.
</p>
