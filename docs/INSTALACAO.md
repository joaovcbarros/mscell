# Instalação e Execução

## Ferramentas usadas

- **Laragon** (traz PHP 8.3.30 e MySQL 8.4.3) — instalado em `C:\laragon`
- **Node.js LTS** — instalado via winget (usado pela ponte do WhatsApp, `whatsapp-bridge/`)
- **Git** — já vinha instalado na máquina

## 1. Banco de dados

O MySQL do Laragon não roda como serviço do Windows por padrão nesta instalação — ele foi
inicializado manualmente com dados em `C:\laragon\data\mysql`.

**Para iniciar o MySQL manualmente** (se não estiver rodando):

```bash
"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" --datadir="C:/laragon/data/mysql" --basedir="C:/laragon/bin/mysql/mysql-8.4.3-winx64" --port=3306 --socket="C:/laragon/tmp/mysql.sock"
```

Deixe essa janela aberta (ou rode em background). Para testar se está no ar:

```bash
"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqladmin.exe" --socket="C:/laragon/tmp/mysql.sock" -u root ping
```

**Para criar/recriar o banco do zero:**

```bash
MYSQL="C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"
"$MYSQL" --socket="C:/laragon/tmp/mysql.sock" -u root --default-character-set=utf8mb4 < database/schema.sql
"$MYSQL" --socket="C:/laragon/tmp/mysql.sock" -u root --default-character-set=utf8mb4 < database/seed.sql
```

> **Importante:** sempre use `--default-character-set=utf8mb4` ao rodar os `.sql` pelo
> cliente `mysql` no Windows. Sem essa flag, acentos (ex: "Película", "Acessórios")
> ficam corrompidos no banco — isso já aconteceu uma vez durante o desenvolvimento e foi
> corrigido reimportando os dados com a flag correta.

O `seed.sql` cria:
- Uma loja inicial ("Loja Principal") — cadastre as demais pela tela **Lojas** (admin) depois do primeiro login
- Usuário admin: `admin@mscell.local` / senha `MsCell@2026` (**troque após o primeiro login**), sem loja fixa (enxerga todas)
- 4 categorias (compartilhadas entre lojas) e 5 produtos de exemplo na Loja Principal

Um usuário de aplicação com privilégios restritos (`mscell_app`, sem acesso de root) também
foi criado no MySQL e é o que o PHP usa para se conectar (veja `.env`).

## 2. Configuração da aplicação

Copie `.env.example` para `.env` (o `.env` real já foi criado durante o desenvolvimento e
**não é versionado** — nunca commite esse arquivo). Ele contém a string de conexão com o
banco, nome da sessão, timezone e o token do webhook do WhatsApp (`WHATSAPP_WEBHOOK_TOKEN`).
Os números de WhatsApp autorizados **não** ficam mais aqui — são cadastrados por loja, na
tela **Lojas** do sistema (veja [WHATSAPP.md](WHATSAPP.md)).

Extensões PHP necessárias (já habilitadas em `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini`):
`mbstring`, `pdo_mysql`, `mysqli`, `openssl`, `curl`, `fileinfo`.

> Por padrão, o PHP que vem no Laragon **não tem `php.ini` habilitado**. Se reinstalar o
> PHP ou usar outra versão, copie `php.ini-development` para `php.ini` na pasta do PHP e
> habilite essas extensões (remova o `;` na frente de cada `extension=...`).

## 3. Rodando o site

### Opção rápida (servidor embutido do PHP, bom para desenvolvimento)

```bash
cd C:\Users\joao\OneDrive\Documentos\MsCell
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -S localhost:8000 -t public
```

Acesse **http://localhost:8000**.

### Opção permanente (via Laragon, com URL fixa)

1. Abra o Laragon (ícone na bandeja do sistema) e clique em **Start All** (inicia Apache e MySQL).
2. Clique com o botão direito no tray do Laragon → **Apache** → **Sites-Enabled** → **Add**,
   ou simplesmente coloque o projeto dentro de `C:\laragon\www\mscell` (o Laragon detecta
   pastas em `www` automaticamente e cria um virtual host `mscell.test`).
3. Para o Laragon apontar para a pasta `public/` (document root) em vez da raiz do projeto,
   configure um virtual host customizado (Laragon → **Menu** → **Apache** → **sites-enabled**
   → editar o `.conf` do site, ajustando `DocumentRoot` para `.../MsCell/public`).
4. Acesse `http://mscell.test`.

Essa etapa 3 é uma configuração feita pela interface gráfica do Laragon — não é algo que dá
para automatizar por linha de comando, por isso fica como passo manual.

## 4. Testando

Veja o passo a passo de teste manual em [../docs/README.md](README.md) ou simplesmente:

1. Acesse o site e faça login com `admin@mscell.local` / `MsCell@2026`
2. Cadastre um produto novo (**Produtos → Novo produto**)
3. Registre uma venda (**Vendas → Nova venda**) e confira se o estoque do produto baixou
4. Crie um usuário com papel "usuário" (**Usuários → Novo usuário**) e confirme que, ao
   logar com ele, os menus de cadastro não aparecem
5. Cadastre uma segunda loja (**Lojas → Nova loja**) e um funcionário vinculado a ela; logue
   com esse funcionário e confirme que ele só vê produtos/vendas/estoque dessa loja (nem por
   URL direta consegue acessar dado de outra)
