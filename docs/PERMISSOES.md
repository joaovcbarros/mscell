# Papéis, Lojas e Permissões

O sistema é **multi-loja**: cada loja tem seu próprio catálogo, estoque e vendas. Todo
usuário (exceto admin) está vinculado a exatamente uma loja e só enxerga/mexe nos dados
dela — mesmo tentando acessar outra URL diretamente.

## Papéis

Três papéis, definidos no cadastro de cada usuário (`usuarios.papel`):

| Ação | admin | funcionário | usuário |
|---|:---:|:---:|:---:|
| Ver dashboard, produtos, vendas e estoque | ✅ | ✅ | ✅ |
| Cadastrar/editar produtos | ✅ | ✅ | ❌ |
| Registrar vendas | ✅ | ✅ | ❌ |
| Ajustar estoque manualmente | ✅ | ✅ | ❌ |
| Ver/revisar mensagens do WhatsApp | ✅ | ✅ | ❌ |
| Gerenciar usuários (criar, editar, ativar/desativar) | ✅ | ❌ | ❌ |
| Gerenciar lojas (criar, editar, número de WhatsApp) | ✅ | ❌ | ❌ |

Na prática:
- **usuário**: papel só de consulta — acompanha vendas, estoque e produtos da própria loja, sem poder alterar nada.
- **funcionário**: opera o dia a dia da própria loja (cadastra produto, vende, ajusta estoque).
- **admin**: enxerga e gerencia **todas as lojas**, mais gestão de usuários e das próprias lojas.

## Escopo por loja

- `funcionário`/`usuário` têm `usuarios.loja_id` fixo — todo dado que veem e criam é sempre
  dessa loja, sem exceção, e isso é garantido no servidor (não é só uma questão de esconder
  botão na tela).
- `admin` tem `loja_id = NULL` e enxerga todas. No topo de cada página tem um **seletor de
  loja** ("Visualizando: Todas as lojas / Loja X / Loja Y") — ele filtra o sistema inteiro
  por uma loja específica, ou mostra tudo agregado (com uma coluna "Loja" extra nas listagens
  para diferenciar). Ao criar um produto ou uma venda vendo "todas as lojas", o admin escolhe
  explicitamente para qual loja é aquele cadastro.

## Como isso é aplicado no código

Toda página protegida chama, logo no início:

```php
use MsCell\Services\AuthService;

AuthService::exigirLogin();                       // qualquer papel logado
AuthService::exigirPapel(['admin', 'funcionario']); // só esses papéis
```

Para o escopo por loja, usa-se:

```php
AuthService::lojaId();          // loja do usuario logado (null so para admin)
AuthService::podeVerTodasLojas(); // true so para admin
AuthService::lojaEfetiva();     // loja para filtrar a consulta desta requisicao
                                 // (funcionario/usuario: sempre a propria; admin: a do seletor, ou null = todas)
```

Todo Model que lista ou busca dados (`Produto::todos()`, `Venda::buscarPorId()`, etc.) aceita
um `$lojaId` opcional — as páginas sempre passam `AuthService::lojaEfetiva()`. Quando o
`$lojaId` não é `null`, um funcionário não consegue ver/editar/vender um item de outra loja
mesmo trocando o `id` na URL: o próprio `SELECT` já filtra pela loja, então o registro
simplesmente "não existe" para ele.

O menu lateral (`public/partials/layout_start.php`) também já esconde os links que o papel
atual não pode acessar e mostra o seletor de loja só para admin — mas a checagem de verdade
é sempre feita nas próprias páginas/Models, não só escondendo o link.
