<?php

namespace MsCell\Models;

use MsCell\Config\Database;

class Venda
{
    public static function todas(array $filtros = [], ?int $lojaId = null): array
    {
        $sql = 'SELECT v.*, u.nome AS usuario_nome, r.nome AS registrado_por_nome, l.nome AS loja_nome
                FROM vendas v
                LEFT JOIN usuarios u ON u.id = v.usuario_id
                LEFT JOIN usuarios r ON r.id = v.registrado_por_id
                LEFT JOIN lojas l ON l.id = v.loja_id
                WHERE 1=1';
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' AND v.loja_id = ?';
            $params[] = $lojaId;
        }

        if (!empty($filtros['data_inicio'])) {
            $sql .= ' AND v.criado_em >= ?';
            $params[] = $filtros['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filtros['data_fim'])) {
            $sql .= ' AND v.criado_em <= ?';
            $params[] = $filtros['data_fim'] . ' 23:59:59';
        }

        if (!empty($filtros['usuario_id'])) {
            $sql .= ' AND v.usuario_id = ?';
            $params[] = $filtros['usuario_id'];
        }

        if (!empty($filtros['origem'])) {
            $sql .= ' AND v.origem = ?';
            $params[] = $filtros['origem'];
        }

        $sql .= ' ORDER BY v.criado_em DESC';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function buscarPorId(int $id, ?int $lojaId = null): ?array
    {
        $sql = 'SELECT v.*, u.nome AS usuario_nome, r.nome AS registrado_por_nome, l.nome AS loja_nome
                FROM vendas v
                LEFT JOIN usuarios u ON u.id = v.usuario_id
                LEFT JOIN usuarios r ON r.id = v.registrado_por_id
                LEFT JOIN lojas l ON l.id = v.loja_id
                WHERE v.id = ?';
        $params = [$id];

        if ($lojaId !== null) {
            $sql .= ' AND v.loja_id = ?';
            $params[] = $lojaId;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: null;
    }

    public static function itens(int $vendaId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT iv.*, p.nome AS produto_nome
             FROM itens_venda iv JOIN produtos p ON p.id = iv.produto_id
             WHERE iv.venda_id = ?'
        );
        $stmt->execute([$vendaId]);

        return $stmt->fetchAll();
    }

    public static function resumoDoDia(?int $lojaId = null, ?int $usuarioId = null): array
    {
        $sql = "SELECT COUNT(*) AS total_vendas, COALESCE(SUM(valor_total), 0) AS total_valor
                FROM vendas
                WHERE status = 'concluida' AND DATE(criado_em) = CURDATE()";
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' AND loja_id = ?';
            $params[] = $lojaId;
        }

        if ($usuarioId !== null) {
            $sql .= ' AND usuario_id = ?';
            $params[] = $usuarioId;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch();
    }

    public static function resumoDoMes(?int $lojaId = null, ?int $usuarioId = null): array
    {
        $sql = "SELECT COUNT(*) AS total_vendas, COALESCE(SUM(valor_total), 0) AS total_valor
                FROM vendas
                WHERE status = 'concluida'
                  AND YEAR(criado_em) = YEAR(CURDATE()) AND MONTH(criado_em) = MONTH(CURDATE())";
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' AND loja_id = ?';
            $params[] = $lojaId;
        }

        if ($usuarioId !== null) {
            $sql .= ' AND usuario_id = ?';
            $params[] = $usuarioId;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch();
    }

    /**
     * Vendas de hoje agrupadas por loja — usado no dashboard do admin
     * quando ele esta olhando "todas as lojas" de uma vez.
     */
    public static function resumoDoDiaPorLoja(): array
    {
        $stmt = Database::getConnection()->query(
            "SELECT l.id AS loja_id, l.nome AS loja_nome,
                    COUNT(v.id) AS total_vendas, COALESCE(SUM(v.valor_total), 0) AS total_valor
             FROM lojas l
             LEFT JOIN vendas v ON v.loja_id = l.id
                AND v.status = 'concluida' AND DATE(v.criado_em) = CURDATE()
             WHERE l.ativa = 1
             GROUP BY l.id, l.nome
             ORDER BY l.nome"
        );

        return $stmt->fetchAll();
    }

    public static function recentes(int $limite = 8, ?int $lojaId = null, ?int $usuarioId = null): array
    {
        $sql = 'SELECT v.*, u.nome AS usuario_nome, l.nome AS loja_nome
                FROM vendas v
                LEFT JOIN usuarios u ON u.id = v.usuario_id
                LEFT JOIN lojas l ON l.id = v.loja_id
                WHERE 1=1';
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' AND v.loja_id = ?';
            $params[] = $lojaId;
        }

        if ($usuarioId !== null) {
            $sql .= ' AND v.usuario_id = ?';
            $params[] = $usuarioId;
        }

        $sql .= ' ORDER BY v.criado_em DESC LIMIT ' . (int) $limite;

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
