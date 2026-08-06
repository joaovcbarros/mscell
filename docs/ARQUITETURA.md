# Arquitetura

## Estrutura de pastas

```
MsCell/
  docs/                 Documentação (este diretório)
  database/
    schema.sql           Criação de todas as tabelas
    seed.sql              Usuário admin inicial + dados de exemplo
  public/                Document root do servidor web
    index.php, login.php, logout.php, dashboard.php
    produtos/  vendas/  estoque/  usuarios/  lojas/  whatsapp/
    partials/             Cabeçalho, rodapé e layout (sidebar + seletor de loja) compartilhados
    assets/css/           CSS próprio (public/assets/css/mscell.css)
  src/                    Código PHP não exposto diretamente ao navegador
    autoload.php           Autoloader PSR-4-like sem Composer (namespace MsCell\)
    bootstrap.php           Ponto de entrada: autoload + .env + sessão + timezone
    Config/
      Env.php                Leitura simples do .env
      Database.php            Conexão PDO (singleton)
    Models/                 Acesso a dados (uma classe por tabela, métodos estáticos)
    Services/               Regras de negócio (autenticação, vendas, estoque, parser)
    Helpers/
      Formatador.php          Formatação de moeda/data/papel para exibição
  api/
    whatsapp_webhook.php    Endpoint chamado pela ponte do WhatsApp (autenticado por token)
  whatsapp-bridge/          Serviço Node.js separado (Baileys) — ver WHATSAPP.md
  .env / .env.example       Configuração local (não versionada) / modelo
```

## Por que PHP puro, sem framework

Decisão tomada no início do projeto: o sistema é de porte pequeno/médio e o objetivo inclui
facilitar manutenção futura sem depender de conhecimento de um framework específico. Em vez
disso, seguimos um padrão simples e consistente:

- **Models** (`src/Models`) fazem apenas acesso a dados via PDO (sem lógica de negócio)
- **Services** (`src/Services`) concentram regras de negócio e validações, e são a camada
  que as páginas em `public/` chamam
- **Páginas em `public/`** são responsáveis só por: checar permissão, ler `$_POST`/`$_GET`,
  chamar um Service, e renderizar HTML

Não há Composer: o autoload (`src/autoload.php`) mapeia `MsCell\Xxx\Classe` para
`src/Xxx/Classe.php` manualmente via `spl_autoload_register`.

## Autenticação e controle de acesso

- Sessão nativa do PHP (`session_start()`), sem tokens/JWT — adequado para um site
  tradicional server-rendered como este.
- `MsCell\Services\AuthService` centraliza login, logout e as checagens de papel:
  - `exigirLogin()` — redireciona para `/login.php` se não houver sessão
  - `exigirPapel(['admin', 'funcionario'])` — redireciona com erro se o papel da sessão
    não estiver na lista permitida
- Cada página protegida chama um desses métodos logo no início, antes de qualquer output.

## Vendas e baixa de estoque

`MsCell\Services\VendaService::registrar()` executa tudo dentro de uma transação PDO:
1. Valida estoque suficiente para cada item
2. Insere a venda e os itens
3. Debita a quantidade vendida do produto (`Produto::ajustarEstoque`)
4. Registra a movimentação de estoque (`MovimentacaoEstoque::registrar`)

Se qualquer passo falhar, a transação é revertida (`rollBack`) e nenhuma alteração fica
parcialmente aplicada. O mesmo método é usado tanto para vendas feitas pela tela **Nova
venda** (`origem = 'sistema'`) quanto para vendas futuras via WhatsApp (`origem = 'whatsapp'`).

## Multi-loja

O sistema atende várias lojas com catálogo/estoque/vendas independentes por loja — detalhes
completos em [PERMISSOES.md](PERMISSOES.md#escopo-por-loja) e [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md).
Resumo para quem for mexer no código: todo Model de listagem/busca aceita um `?int $lojaId`
opcional, e toda página passa `AuthService::lojaEfetiva()` — nunca confie em `loja_id` vindo
de `$_POST`/`$_GET` para decidir o que gravar quando o usuário não for admin (use sempre
`AuthService::lojaId()`/`lojaEfetiva()`, calculados no servidor a partir da sessão).

## Frontend

Bootstrap 5 (via CDN) para os componentes visuais + um CSS próprio pequeno
(`public/assets/css/mscell.css`) com a paleta de cores do sistema (azul-marinho/ciano,
inspirado num visual "tech" combinando com o segmento de assistência técnica). Não há
build step (sem Webpack/Vite) — é HTML renderizado no servidor com um pouco de JavaScript
puro só na tela de nova venda (para adicionar itens dinamicamente).
