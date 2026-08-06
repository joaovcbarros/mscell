require('dotenv').config();

const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
} = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const pino = require('pino');
const qrcodeTerminal = require('qrcode-terminal');
const QRCode = require('qrcode');
const axios = require('axios');

// SESSION_DIR permite rodar mais de uma instancia da ponte (uma por loja)
// reaproveitando o mesmo codigo/node_modules, cada uma com sua propria
// sessao pareada a um numero de WhatsApp diferente. Ex:
//   SESSION_DIR=./session-loja2 node index.js
const path = require('path');
const DIRETORIO_SESSAO = process.env.SESSION_DIR
    ? path.resolve(__dirname, process.env.SESSION_DIR)
    : path.join(__dirname, 'session');
// Nome do PNG do QR inclui a pasta de sessao, para nao colidir quando
// mais de uma instancia (uma por loja) roda ao mesmo tempo.
const CAMINHO_QR_PNG = path.join(__dirname, `qr-${path.basename(DIRETORIO_SESSAO)}.png`);

const WEBHOOK_URL = process.env.WEBHOOK_URL;
const WEBHOOK_TOKEN = process.env.WEBHOOK_TOKEN;

if (!WEBHOOK_URL || !WEBHOOK_TOKEN) {
    console.error('Configure WEBHOOK_URL e WEBHOOK_TOKEN em whatsapp-bridge/.env antes de rodar.');
    process.exit(1);
}

// IDs de mensagens enviadas pela propria ponte, para nunca reprocessar
// a propria resposta como se fosse um novo comando (evita loop infinito).
const idsEnviadosPelaPonte = new Set();

async function iniciar() {
    const { state, saveCreds } = await useMultiFileAuthState(DIRETORIO_SESSAO);
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        auth: state,
        logger: pino({ level: 'silent' }),
        printQRInTerminal: false,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log('\nEscaneie este QR Code no WhatsApp do celular (Aparelhos conectados > Conectar um aparelho):\n');
            qrcodeTerminal.generate(qr, { small: true });
            QRCode.toFile(CAMINHO_QR_PNG, qr, { width: 400 })
                .then(() => console.log(`QR Code tambem salvo em: ${CAMINHO_QR_PNG}`))
                .catch((e) => console.error('Falha ao salvar QR como imagem:', e.message));
        }

        if (connection === 'close') {
            const codigo = lastDisconnect?.error instanceof Boom
                ? lastDisconnect.error.output.statusCode
                : null;
            const deveReconectar = codigo !== DisconnectReason.loggedOut;

            console.log('Conexao encerrada.', deveReconectar ? 'Reconectando em 3s...' : 'Sessao deslogada, apague a pasta session/ para parear novamente.');

            if (deveReconectar) {
                setTimeout(iniciar, 3000);
            }
        } else if (connection === 'open') {
            const numeroProprio = sock.user.id.split(':')[0].split('@')[0];
            console.log(`\nConectado ao WhatsApp com o numero ${numeroProprio}.`);
            console.log('Cadastre esse numero na loja correspondente (tela Lojas > numero de WhatsApp).');
            console.log('Envie mensagens no chat "Mensagem para voce mesmo" para registrar vendas.\n');
        }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') {
            return;
        }

        for (const msg of messages) {
            await processarMensagem(sock, msg);
        }
    });
}

async function processarMensagem(sock, msg) {
    if (!msg.message || !msg.key?.id) {
        return;
    }

    if (idsEnviadosPelaPonte.has(msg.key.id)) {
        return;
    }

    const numeroProprio = sock.user.id.split(':')[0].split('@')[0];
    const jidProprio = `${numeroProprio}@s.whatsapp.net`;
    // O WhatsApp pode identificar o chat "Mensagem para voce mesmo" tanto pelo
    // JID tradicional (numero@s.whatsapp.net) quanto pelo LID (identificador
    // "linked ID" mais novo, numero-opaco@lid) dependendo da conta/versao.
    const lidProprio = sock.user.lid ? `${sock.user.lid.split(':')[0]}@lid` : null;

    const remoteJid = msg.key.remoteJid;

    // So processa mensagens no chat "Mensagem para voce mesmo" (self-chat).
    // Isso evita que conversas normais sejam interpretadas como comandos.
    if (remoteJid !== jidProprio && remoteJid !== lidProprio) {
        return;
    }

    const texto = msg.message.conversation
        || msg.message.extendedTextMessage?.text
        || '';

    if (!texto.trim()) {
        return;
    }

    console.log(`[recebida] ${texto}`);

    let textoResposta;

    try {
        const resposta = await axios.post(WEBHOOK_URL, {
            numero_origem: numeroProprio,
            mensagem: texto,
        }, {
            headers: { Authorization: `Bearer ${WEBHOOK_TOKEN}` },
            timeout: 10000,
        });

        textoResposta = resposta.data?.resposta || 'Mensagem processada.';
    } catch (erro) {
        const detalhe = erro.response?.data?.erro || erro.message;
        console.error('[erro ao chamar o webhook]', detalhe);
        textoResposta = `⚠️ Nao consegui falar com o sistema: ${detalhe}`;
    }

    const enviada = await sock.sendMessage(remoteJid, { text: textoResposta });
    if (enviada?.key?.id) {
        idsEnviadosPelaPonte.add(enviada.key.id);
    }
}

iniciar();
