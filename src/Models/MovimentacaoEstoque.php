<?php

namespace MsCell\Models;

use MsCell\Config\Database;

class MovimentacaoEstoque
{
    public const ENTRADA = 'entrada';
    public const SAIDA = 'saida';
    public const AJUSTE = 'ajuste';

    public static function registrar(
        int $produtoId,
        string $tipo,
        int $quantidade,
        ?string $motivo,
        ?int $vendaId,
        ?int $usuarioId
    ): int {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, motivo, venda_id, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$produtoId, $tipo, $quantidade, $motivo, $vendaId, $usuarioId]);

        return (int) Database::getConnection()->lastInsertId();
    }

    public static function porProduto(int $produtoId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT m.*, u.nome AS usuario_nome
             FROM movimentacoes_estoque m
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.produto_id = ?
             ORDER BY m.criado_em DESC'
        );
        $stmt->execute([$produtoId]);

        return $stmt->fetchAll();
    }

    public static function recentes(int $limite = 50, ?int $lojaId = null): array
    {
        $sql = 'SELECT m.*, p.nome AS produto_nome, u.nome AS usuario_nome, p.loja_id, l.nome AS loja_nome
                FROM movimentacoes_estoque m
                JOIN produtos p ON p.id = m.produto_id
                LEFT JOIN usuarios u ON u.id = m.usuario_id
                LEFT JOIN lojas l ON l.id = p.loja_id
                WHERE 1=1';
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' AND p.loja_id = ?';
            $params[] = $lojaId;
        }

        $sql .= ' ORDER BY m.criado_em DESC LIMIT ' . (int) $limite;

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
