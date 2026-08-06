# Integração com WhatsApp

Quando alguém manda uma mensagem tipo *"vendi iphone 15 pro max 5.000"* no WhatsApp, o
sistema cadastra a venda automaticamente — baixando o estoque do produto certo, **na loja
certa**.

## Como funciona

```
WhatsApp (celular)  →  whatsapp-bridge/ (Node.js + Baileys)  →  api/whatsapp_webhook.php  →  banco de dados
```

- **`whatsapp-bridge/`** (Node.js, biblioteca `@whiskeysockets/baileys`) é a ponte: conecta
  a **uma conta de WhatsApp** via QR Code (como o WhatsApp Web) e só repassa mensagens/respostas
  — toda a lógica de negócio fica no PHP.
- Cada loja com WhatsApp ativo roda **sua própria instância** da ponte, pareada com o número
  daquela loja (ver "Múltiplas lojas" abaixo).
- **`api/whatsapp_webhook.php`** recebe `{numero_origem, mensagem}`, autenticado por um token
  secreto compartilhado (`WHATSAPP_WEBHOOK_TOKEN` no `.env`). Ele:
  1. Descobre a loja a partir do número (`lojas.numero_whatsapp`) — se o número não pertencer
     a nenhuma loja ativa, rejeita (`403`).
  2. Chama `WhatsappVendaService::processarMensagem()`, que usa `MensagemVendaParser` para
     interpretar o texto (produto, quantidade, valor) restrito ao catálogo **daquela loja**.
  3. Se reconhecer com confiança suficiente: registra a venda (mesmo `VendaService` da tela),
     baixa o estoque e responde confirmando.
  4. Se achar um valor mas não reconhecer o produto: pergunta se deve cadastrar como produto
     novo (ver "Produto novo" abaixo).
  5. Se não entender nada: pede mais detalhes. Tudo fica registrado em
     `whatsapp_mensagens_log` (tela **Mensagens WhatsApp** no sistema).

## Como usar no dia a dia (chat "Mensagem para você mesmo")

A forma mais simples de operar com um único celular: a ponte é pareada com o WhatsApp da
própria loja, e quem manda as mensagens de venda é **esse mesmo número**, usando o chat
*"Mensagem para você mesmo"* do WhatsApp (o de anotações pessoais). A ponte só reage a
mensagens desse chat específico — conversas normais não disparam nada.

1. No celular da loja, abra o WhatsApp → **Mensagem para você mesmo**.
2. Digite algo como `vendi iphone 15 pro max 5000`.
3. O sistema responde na hora confirmando a venda (com emoji da categoria, ex: 📱 celular,
   🎧 acessório, 🔧 peça, 🛠️ serviço).

## Formato de mensagem

O parser tenta reconhecer frases soltas: `"vendi iphone 15 pro max 5.000"`,
`"venda: fone bluetooth 100"`, `"vendi 2x pelicula 25,00"`, `"vendeu troca de tela 13 por 600
reais"`. Quanto mais completo o campo **apelidos** de cada produto (tela Produtos →
Novo/Editar), melhor o reconhecimento. O número do modelo é levado em conta — "iphone 12" não
é confundido com "iphone 15" mesmo que o resto do texto seja parecido.

## Produto novo (perguntar antes de cadastrar)

Se a mensagem tem um valor mas o produto não bate com nada cadastrado, o sistema **não**
inventa um cadastro sozinho: ele responde perguntando, por exemplo:

> 🤔 Não encontrei "galaxy a54" cadastrado. Quer que eu cadastre esse produto novo com preço
> de venda R$ 1.200,00 e já registre essa venda? Responda *sim* para confirmar ou *não* para
> cancelar.

- Respondendo **sim** (ou variações: `s`, `ok`, `confirmar`...): cadastra o produto (com o
  nome extraído da mensagem, preço de venda = valor informado, estoque inicial = quantidade
  vendida) e já registra a venda — o produto some do estoque, ficando pronto para você
  completar categoria/apelidos/preço de custo depois pela tela.
- Respondendo **não**: cancela, nada é criado.
- A pergunta expira em 10 minutos ou se você mandar outra coisa sem responder sim/não — nesse
  caso a nova mensagem é processada normalmente.

## Múltiplas lojas

Cada loja (tela **Lojas**, só admin) tem um campo **Número do WhatsApp** — é o número que a
ponte Node daquela loja vai parear. O webhook usa esse número para saber de qual loja veio a
mensagem, então **cada loja só reconhece o próprio catálogo** (o mesmo nome de produto em
duas lojas diferentes não se confunde).

Para rodar a ponte de uma segunda loja, use uma pasta de sessão separada (mesmo código,
`node_modules` reaproveitado):

```bash
# Loja 1 (padrão)
node index.js

# Loja 2 — sessão separada, mesmo webhook/token
SESSION_DIR=./session-loja2 node index.js
```

Cada instância vai gerar seu próprio QR Code na primeira vez — escaneie com o celular
daquela loja.

## Configuração (`.env` na raiz do projeto)

```
WHATSAPP_WEBHOOK_TOKEN=<segredo compartilhado por todas as pontes>
```

Não existe mais uma lista de números autorizados no `.env` — isso agora é por loja, editável
direto na tela **Lojas** do sistema (campo "Número do WhatsApp").

`whatsapp-bridge/.env` (por instância):
```
WEBHOOK_URL=http://localhost:8000/api/whatsapp_webhook.php
WEBHOOK_TOKEN=<mesmo valor do WHATSAPP_WEBHOOK_TOKEN>
```

## Rodando a ponte

```bash
cd whatsapp-bridge
npm install        # so na primeira vez
node index.js
```

Escaneie o QR Code (aparece no terminal e também é salvo como `qr-session.png`, ou
`qr-<nome da pasta de sessão>.png` se você usou `SESSION_DIR`) pelo WhatsApp do celular da
loja: **Configurações → Aparelhos conectados → Conectar um aparelho**.

## Risco (já assumido conscientemente)

Baileys é uma biblioteca não-oficial — funciona simulando um "aparelho conectado" do
WhatsApp Web, fora do método suportado pela Meta. Existe um risco baixo, porém real, de
bloqueio do número se usado de forma muito agressiva (muitas mensagens automáticas em pouco
tempo, por exemplo). Para uso normal (algumas vendas por dia, respondendo no chat pessoal),
o risco é baixo. Migrar para a WhatsApp Cloud API oficial é uma evolução possível no futuro,
caso vire um problema.
