<?php

namespace MsCell\Models;

use MsCell\Config\Database;

class MensagemWhatsapp
{
    public static function registrar(
        string $numeroOrigem,
        ?int $lojaId,
        string $mensagemBruta,
        array $interpretacao,
        ?int $vendaId,
        string $status
    ): int {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO whatsapp_mensagens_log
                (numero_origem, loja_id, mensagem_bruta, interpretacao_json, venda_id, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $numeroOrigem,
            $lojaId,
            $mensagemBruta,
            json_encode($interpretacao, JSON_UNESCAPED_UNICODE),
            $vendaId,
            $status,
        ]);

        return (int) Database::getConnection()->lastInsertId();
    }

    public static function pendentesDeRevisao(?int $lojaId = null): array
    {
        $sql = "SELECT * FROM whatsapp_mensagens_log WHERE status = 'revisao'";
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' AND loja_id = ?';
            $params[] = $lojaId;
        }

        $sql .= ' ORDER BY criado_em DESC';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function recentes(int $limite = 20, ?int $lojaId = null): array
    {
        $sql = 'SELECT m.*, l.nome AS loja_nome FROM whatsapp_mensagens_log m
                LEFT JOIN lojas l ON l.id = m.loja_id
                WHERE 1=1';
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' AND m.loja_id = ?';
            $params[] = $lojaId;
        }

        $sql .= ' ORDER BY m.criado_em DESC LIMIT ' . (int) $limite;

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
