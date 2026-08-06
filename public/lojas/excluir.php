<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Models\Loja;
use MsCell\Services\AuthService;

AuthService::exigirPapel(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lojas/index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

try {
    Loja::excluir($id);
    header('Location: /lojas/index.php?excluida=1');
} catch (\Throwable $e) {
    header('Location: /lojas/index.php?erro=' . urlencode($e->getMessage()));
}
exit;
