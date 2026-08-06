<?php

namespace MsCell\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = Env::get('DB_HOST', 'localhost');
        $port = Env::get('DB_PORT', '3306');
        $name = Env::get('DB_NAME', 'mscell');
        $user = Env::get('DB_USER', 'mscell_app');
        $pass = Env::get('DB_PASS', '');
        $socket = Env::get('DB_SOCKET');

        $dsn = $socket
            ? "mysql:unix_socket={$socket};dbname={$name};charset=utf8mb4"
            : "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('[MsCell] Falha na conexao com o banco: ' . $e->getMessage());
            throw new PDOException('Nao foi possivel conectar ao banco de dados.', (int) $e->getCode());
        }

        return self::$instance;
    }
}
