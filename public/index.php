<?php

require_once __DIR__ . '/../src/bootstrap.php';

use MsCell\Services\AuthService;

header('Location: ' . (AuthService::usuarioLogado() ? '/dashboard.php' : '/login.php'));
exit;
