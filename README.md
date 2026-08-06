<p align="center">
  <img src="public/assets/img/logo.png" alt="MsCell" height="120">
</p>

<h1 align="center">MsCell — Sistema de Gestão Multi-Loja</h1>

<p align="center">
  Sistema web feito sob medida para uma assistência técnica de celulares real, com controle de
  produtos, estoque e vendas para múltiplas lojas — e cadastro automático de vendas via WhatsApp.
</p>

---

## Sobre o projeto

A **MsCell** ([@mscellassistencia](https://www.instagram.com/mscellassistencia/)) é uma assistência
técnica de celulares com mais de uma loja física. Este sistema nasceu de uma necessidade real: dar
ao dono uma visão centralizada de produtos, estoque e vendas de todas as lojas, com controle de
acesso por papel e por loja para os funcionários — e, como diferencial, permitir registrar uma
venda simplesmente **mandando uma mensagem no WhatsApp**.

Construído do zero: modelagem de banco, backend em PHP puro, frontend server-rendered, e uma ponte
em Node.js que conecta ao WhatsApp e interpreta linguagem natural para cadastrar vendas
automaticamente.

## Funcionalidades

- 🏬 **Multi-loja** — cada loja com seu próprio catálogo, estoque e vendas; admin alterna entre
  "ver tudo" ou uma loja específica; funcionário só acessa a loja em que trabalha (garantido no
  servidor, não só escondido na tela)
- 🔐 **Papéis de acesso** — admin / funcionário / usuário, cada um com permissões diferentes
- 📦 **Produtos e estoque** — cadastro completo, alerta de estoque baixo, histórico de todas as
  movimentações (entrada, saída, ajuste)
- 🧾 **Vendas** — múltiplos itens por venda, baixa automática de estoque, histórico com filtros
- 📊 **Dashboard** — vendas do dia/mês, agregadas ou por loja
- 💬 **Integração com WhatsApp** — manda `"vendi iphone 15 pro max 5000"` no chat e o sistema:
  - interpreta produto, quantidade e valor por texto livre (sem IA/LLM — parser próprio com
    regex + correspondência aproximada, rodando 100% local)
  - identifica a loja automaticamente pelo número que recebeu a mensagem
  - se não reconhece o produto, **pergunta** se deve cadastrar como novo antes de agir
  - responde confirmando a venda, com emoji da categoria

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.3 puro (orientado a objetos, PDO) — sem framework |
| Banco de dados | MySQL 8.4 |
| Frontend | Server-rendered (PHP) + Bootstrap 5 |
| Integração WhatsApp | Node.js + [Baileys](https://github.com/WhiskeySockets/Baileys) |
| Ambiente local | Laragon |

Decisão consciente de não usar framework: projeto de porte pequeno/médio, priorizando código
simples de ler e manter — `Models` cuidam só de acesso a dados, `Services` concentram as regras de
negócio, as páginas em `public/` só orquestram. Detalhes em [`docs/ARQUITETURA.md`](docs/ARQUITETURA.md).

## Estrutura

```
public/     Document root — páginas por módulo (produtos, vendas, estoque, lojas, usuários...)
src/        Models, Services e Helpers (PHP)
api/        Webhook do WhatsApp
database/   Schema SQL + dados de exemplo
whatsapp-bridge/   Ponte Node.js (Baileys) que conecta ao WhatsApp
docs/       Documentação completa do projeto
```

## Documentação

- [Instalação e execução](docs/INSTALACAO.md)
- [Arquitetura](docs/ARQUITETURA.md)
- [Modelo de dados](docs/BANCO_DE_DADOS.md)
- [Papéis, lojas e permissões](docs/PERMISSOES.md)
- [Integração com WhatsApp](docs/WHATSAPP.md)

## Rodando localmente

```bash
# 1. Banco de dados
mysql --default-character-set=utf8mb4 -u root < database/schema.sql
mysql --default-character-set=utf8mb4 -u root < database/seed.sql

# 2. Configuração
cp .env.example .env   # ajuste com os dados do seu banco

# 3. Servidor
php -S localhost:8000 -t public
```

Acesse `http://localhost:8000` — login inicial: `admin@mscell.local` / `MsCell@2026`. Passo a
passo completo (incluindo a ponte do WhatsApp) em [`docs/INSTALACAO.md`](docs/INSTALACAO.md).

## Status

Projeto ativo, em uso real e em evolução contínua.

---

<p align="center">Feito com PHP puro, café e um pouco de regex. 📱</p>
