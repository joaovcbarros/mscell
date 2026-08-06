<?php

/**
 * Ponto de entrada carregado por toda pagina em public/ e api/.
 * Registra o autoloader, carrega o .env, configura timezone e sessao.
 */

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use MsCell\Config\Env;

define('BASE_PATH', dirname(__DIR__));

Env::load(BASE_PATH . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'America/Sao_Paulo'));

if (session_status() === PHP_SESSION_NONE) {
    session_name(Env::get('APP_SESSION_NAME', 'mscell_session'));
    session_start();
}

// Todas as paginas sao dinamicas/autenticadas: nunca deixar o navegador
// (nem o cache de "voltar/avancar") reaproveitar uma versao antiga com
// dados de outro registro (ex: formulario de edicao de outra loja/produto).
if (PHP_SAPI !== 'cli') {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
