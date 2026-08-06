# MsCell — Sistema de Gestão

Sistema web multi-loja para gestão de produtos, estoque, vendas e usuários, desenvolvido
para a assistência técnica **MsCell** ([@mscellassistencia](https://www.instagram.com/mscellassistencia/)).

## O que o sistema faz hoje

- **Multi-loja**: cada loja tem seu próprio catálogo, estoque e vendas; admin gerencia
  todas, cada funcionário só enxerga a sua (veja [PERMISSOES.md](PERMISSOES.md))
- **Login com papéis de acesso**: admin / funcionário / usuário
- **Cadastro de produtos**: nome, categoria, SKU, preço de custo/venda, estoque atual e mínimo
- **Registro de vendas**: múltiplos itens por venda, baixa automática de estoque, histórico com filtros
- **Controle de estoque**: histórico de movimentações (entrada/saída/ajuste) e ajuste manual
- **Dashboard**: vendas do dia/mês, produtos com estoque baixo, últimas vendas — agregado ou
  por loja, conforme o seletor no topo
- **Gestão de usuários** (admin): criar, editar, ativar/desativar contas, vincular a uma loja
- **Gestão de lojas** (admin): cadastrar lojas, endereço e número de WhatsApp de cada uma
- **Integração com WhatsApp**: mensagens tipo `"vendi iphone 15 pro max 5000"` cadastram a
  venda automaticamente, na loja certa (ver [WHATSAPP.md](WHATSAPP.md))

## Documentos

- [INSTALACAO.md](INSTALACAO.md) — como rodar o projeto do zero
- [ARQUITETURA.md](ARQUITETURA.md) — estrutura de pastas e decisões técnicas
- [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md) — modelo de dados
- [PERMISSOES.md](PERMISSOES.md) — papéis, lojas e o que cada um pode fazer
- [WHATSAPP.md](WHATSAPP.md) — como funciona a integração com WhatsApp

## Stack

PHP 8.3 puro (orientado a objetos, com PDO) + MySQL 8.4, sem framework. Ambiente local
gerenciado pelo Laragon. Sem Composer — autoload próprio simples (`src/autoload.php`).
Ponte de WhatsApp em Node.js (`whatsapp-bridge/`, biblioteca Baileys).
