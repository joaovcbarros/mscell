<?php

require_once __DIR__ . '/../src/bootstrap.php';

use MsCell\Services\AuthService;

AuthService::logout();
header('Location: /login.php');
exit;
