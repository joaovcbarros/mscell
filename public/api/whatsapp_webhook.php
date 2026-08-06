<?php

/**
 * Wrapper para expor o endpoint real (api/whatsapp_webhook.php, fora do
 * document root) em /api/whatsapp_webhook.php, alcançável tanto pelo
 * servidor de desenvolvimento quanto pelo virtual host do Laragon
 * (ambos apontando para public/ como document root).
 */

require __DIR__ . '/../../api/whatsapp_webhook.php';
