<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use MsCell\Services\AuthService;

AuthService::exigirPapel(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lojaId = $_POST['loja_id'] !== '' ? (int) $_POST['loja_id'] : null;
    AuthService::definirLojaFiltro($lojaId);
}

$voltar = $_POST['voltar'] ?? '/dashboard.php';
if (!str_starts_with($voltar, '/') || str_starts_with($voltar, '//')) {
    $voltar = '/dashboard.php';
}

header('Location: ' . $voltar);
exit;
