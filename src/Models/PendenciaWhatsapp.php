<?php

namespace MsCell\Models;

use MsCell\Config\Database;

/**
 * Confirmacoes pendentes de "cadastrar produto novo?" feitas via WhatsApp,
 * aguardando a resposta (sim/nao) na proxima mensagem do mesmo numero.
 */
class PendenciaWhatsapp
{
    public static function criar(
        string $numeroOrigem,
        ?int $lojaId,
        string $textoProduto,
        float $valor,
        int $quantidade,
        int $minutosValidade = 10
    ): int {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO whatsapp_pendencias (numero_origem, loja_id, texto_produto, valor, quantidade, expira_em)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
        );
        $stmt->execute([$numeroOrigem, $lojaId, $textoProduto, $valor, $quantidade, $minutosValidade]);

        return (int) Database::getConnection()->lastInsertId();
    }

    public static function buscarAguardando(string $numeroOrigem): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM whatsapp_pendencias
             WHERE numero_origem = ? AND status = 'aguardando' AND expira_em >= NOW()
             ORDER BY criado_em DESC LIMIT 1"
        );
        $stmt->execute([$numeroOrigem]);

        return $stmt->fetch() ?: null;
    }

    public static function marcarStatus(int $id, string $status): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE whatsapp_pendencias SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }
}
