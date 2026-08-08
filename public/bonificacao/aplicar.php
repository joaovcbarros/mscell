<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\MetaBonificacao;
use MsCell\Services\AuthService;
use MsCell\Services\BonificacaoService;

AuthService::exigirPapel(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $meta = MetaBonificacao::buscarPorId($id);

    // So marca como aplicada se a meta realmente foi batida — evita
    // aplicar bonus por engano numa meta ainda em andamento.
    if ($meta && !$meta['aplicada'] && BonificacaoService::avaliar($meta)['atingida']) {
        MetaBonificacao::marcarAplicada($id);
        header('Location: /bonificacao/index.php?aplicada=1');
        exit;
    }
}

header('Location: /bonificacao/index.php');
exit;
