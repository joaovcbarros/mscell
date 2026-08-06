# Modelo de Dados

Banco `mscell` (MySQL/MariaDB, `utf8mb4`). Definição completa em
[`database/schema.sql`](../database/schema.sql).

## Tabelas

### `lojas`
Cada loja física do negócio. O sistema é multi-loja: cada loja tem seu próprio catálogo de
produtos/estoque e, opcionalmente, seu próprio número de WhatsApp.
| Campo | Descrição |
|---|---|
| `numero_whatsapp` | número (só dígitos) da ponte Node dessa loja; é como o webhook sabe de qual loja veio cada mensagem — veja [WHATSAPP.md](WHATSAPP.md) |
| `ativa` | lojas desativadas somem dos formulários de seleção, mas os dados históricos continuam intactos |

### `usuarios`
Contas de acesso ao sistema.
| Campo | Descrição |
|---|---|
| `papel` | `admin`, `funcionario` ou `usuario` — veja [PERMISSOES.md](PERMISSOES.md) |
| `loja_id` | loja a qual o usuário está vinculado. `NULL` só faz sentido para `admin` (enxerga todas as lojas); `funcionario`/`usuario` sempre têm uma loja definida e só acessam dados dela |
| `senha_hash` | gerada com `password_hash()` (bcrypt), nunca texto puro |
| `ativo` | usuários desativados não conseguem logar |

### `categorias`
Agrupamento simples de produtos (Celulares, Acessórios, Peças, Serviços...). **Compartilhado
entre todas as lojas** — não tem `loja_id`.

### `produtos`
Catálogo com controle de estoque, **sempre pertencente a uma loja** (`loja_id NOT NULL`).
Cada loja tem seu próprio catálogo/estoque independente — o mesmo modelo de celular vendido
em duas lojas vira dois registros de produto separados, um em cada loja.
| Campo | Descrição |
|---|---|
| `loja_id` | loja dona do produto; nunca muda depois de criado |
| `apelidos` | variações do nome separadas por `\|` (ex: `iphone 15 pro max\|15 pro max\|15pm`), usadas pelo parser de mensagens do WhatsApp para reconhecer o produto em texto livre — a busca já é restrita à loja de quem mandou a mensagem |
| `quantidade_estoque` | só é alterada por `Produto::ajustarEstoque()`, nunca editada direto por um formulário |
| `estoque_minimo` | usado para o alerta de "estoque baixo" no dashboard |

### `vendas`
Cabeçalho de cada venda, **sempre vinculada a uma loja** (`loja_id NOT NULL`, gravado no
momento da venda).
| Campo | Descrição |
|---|---|
| `loja_id` | loja onde a venda ocorreu |
| `origem` | `sistema` (registrada pela tela) ou `whatsapp` (automática) |
| `usuario_id` | quem registrou; pode ser `NULL` em vendas automáticas do WhatsApp |

### `itens_venda`
Um registro por produto vendido dentro de uma venda (`venda_id` → `vendas.id`).

### `movimentacoes_estoque`
Histórico de toda alteração de estoque — entrada, saída ou ajuste — com o motivo, quem fez
e, se aplicável, a venda relacionada (`venda_id`). Toda venda gera automaticamente uma
movimentação do tipo `saida` para cada item. **Não tem `loja_id` próprio** — a loja é sempre
a do produto (`produto_id` → `produtos.loja_id`).

### `whatsapp_mensagens_log`
Auditoria de mensagens recebidas via WhatsApp e como o sistema interpretou cada uma
(ver [WHATSAPP.md](WHATSAPP.md)). `loja_id` é resolvido a partir de `lojas.numero_whatsapp`
(quem recebeu a mensagem); fica `NULL` se o número não pertencer a nenhuma loja cadastrada
(nesse caso a mensagem nem chega a ser processada). `status`:
- `processada` — venda cadastrada automaticamente
- `revisao` — o sistema não teve confiança suficiente; precisa ser tratada manualmente
- `falha` — erro ao tentar cadastrar a venda (ex: estoque insuficiente)

### `whatsapp_pendencias`
Quando o parser identifica um valor mas não reconhece o produto, fica aguardando aqui uma
confirmação (a próxima mensagem do mesmo número) para cadastrar o produto novo — na loja
certa — e registrar a venda. Ver [WHATSAPP.md](WHATSAPP.md).

## Relacionamentos

```
lojas 1───N usuarios (funcionario/usuario; admin fica com loja_id NULL)
lojas 1───N produtos
lojas 1───N vendas
lojas 1───N whatsapp_mensagens_log / whatsapp_pendencias
usuarios 1───N vendas
usuarios 1───N movimentacoes_estoque
categorias 1───N produtos (compartilhado entre lojas)
produtos 1───N itens_venda
produtos 1───N movimentacoes_estoque
vendas 1───N itens_venda
vendas 1───N movimentacoes_estoque (opcional, quando a origem é uma venda)
vendas 1───1 whatsapp_mensagens_log (opcional, quando a origem é o WhatsApp)
```
