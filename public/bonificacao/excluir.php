<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\MetaBonificacao;
use MsCell\Services\AuthService;

AuthService::exigirPapel(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bonificacao/index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$excluida = MetaBonificacao::excluir($id);

header('Location: /bonificacao/index.php?' . ($excluida ? 'excluida=1' : 'erro=' . urlencode('Só é possível excluir metas ainda ativas (não processadas).')));
exit;
