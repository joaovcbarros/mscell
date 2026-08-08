<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\MetaBonificacao;
use MsCell\Services\AuthService;
use MsCell\Services\BonificacaoService;

AuthService::exigirPapel(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bonificacao/index.php');
    exit;
}

$periodoInicio = $_POST['periodo_inicio'] ?? '';
$periodoFim = $_POST['periodo_fim'] ?? '';

if ($periodoInicio === '' || $periodoFim === '') {
    header('Location: /bonificacao/index.php');
    exit;
}

// Nunca confia numa lista de IDs vinda do cliente: busca de novo, no
// servidor, quais metas ATIVAS realmente batem esse periodo (e a loja
// que o admin estava vendo), e so processa essas.
$lojaEfetiva = AuthService::lojaEfetiva();
$metas = MetaBonificacao::todas($lojaEfetiva, 'ativa', $periodoInicio, $periodoFim);

foreach ($metas as $meta) {
    $resultado = BonificacaoService::processar($meta);
    MetaBonificacao::marcarProcessada((int) $meta['id'], $resultado);
    MetaBonificacao::criarProximoPeriodo($meta);
}

header('Location: /bonificacao/index.php?processado=1&periodo_inicio=' . urlencode($periodoInicio) . '&periodo_fim=' . urlencode($periodoFim) . '&status=processada');
exit;
