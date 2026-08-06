<?php

/**
 * Endpoint chamado pela ponte Node (whatsapp-bridge) sempre que uma
 * mensagem chega no WhatsApp conectado. Nao e destinado a ser acessado
 * pelo navegador: exige um token secreto compartilhado (Bearer token).
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use MsCell\Config\Env;
use MsCell\Models\Loja;
use MsCell\Services\WhatsappVendaService;

header('Content-Type: application/json; charset=utf-8');

function responder(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['erro' => 'Metodo nao permitido.']);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$tokenEsperado = Env::get('WHATSAPP_WEBHOOK_TOKEN', '');

if ($tokenEsperado === '' || $authHeader !== "Bearer {$tokenEsperado}") {
    responder(401, ['erro' => 'Token invalido.']);
}

$corpo = json_decode(file_get_contents('php://input'), true);

if (!is_array($corpo) || empty($corpo['numero_origem']) || !isset($corpo['mensagem'])) {
    responder(400, ['erro' => 'Envie "numero_origem" e "mensagem" no corpo JSON.']);
}

$numeroOrigem = preg_replace('/\D/', '', (string) $corpo['numero_origem']);
$mensagem = trim((string) $corpo['mensagem']);

if ($mensagem === '') {
    responder(400, ['erro' => 'Mensagem vazia.']);
}

// A loja e resolvida pelo numero que recebeu a mensagem (cada loja tem
// seu proprio numero de WhatsApp cadastrado em Lojas > numero_whatsapp).
// Isso substitui a antiga whitelist fixa em .env, permitindo cadastrar
// quantas lojas/numeros forem necessarios direto pela tela de Lojas.
$loja = Loja::buscarPorNumeroWhatsapp($numeroOrigem);

if ($loja === null) {
    responder(403, ['erro' => 'Numero nao vinculado a nenhuma loja ativa. Cadastre-o em Lojas > numero de WhatsApp.']);
}

$resultado = WhatsappVendaService::processarMensagem((int) $loja['id'], $numeroOrigem, $mensagem);

responder(200, $resultado);
