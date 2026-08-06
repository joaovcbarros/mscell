<?php

namespace MsCell\Models;

use MsCell\Config\Database;

class Produto
{
    public static function todos(bool $apenasAtivos = false, ?int $lojaId = null): array
    {
        $sql = 'SELECT p.*, c.nome AS categoria_nome, l.nome AS loja_nome
                FROM produtos p
                LEFT JOIN categorias c ON c.id = p.categoria_id
                LEFT JOIN lojas l ON l.id = p.loja_id
                WHERE 1=1';
        $params = [];

        if ($apenasAtivos) {
            $sql .= ' AND p.ativo = 1';
        }

        if ($lojaId !== null) {
            $sql .= ' AND p.loja_id = ?';
            $params[] = $lojaId;
        }

        $sql .= ' ORDER BY p.nome';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Busca por id, opcionalmente restringindo a uma loja (retorna null
     * se o produto existir mas pertencer a outra loja — usado para
     * impedir que um funcionario acesse/edite produto de outra loja
     * so trocando o id na URL).
     */
    public static function buscarPorId(int $id, ?int $lojaId = null): ?array
    {
        $sql = 'SELECT p.*, c.nome AS categoria_nome, l.nome AS loja_nome
                FROM produtos p
                LEFT JOIN categorias c ON c.id = p.categoria_id
                LEFT JOIN lojas l ON l.id = p.loja_id
                WHERE p.id = ?';
        $params = [$id];

        if ($lojaId !== null) {
            $sql .= ' AND p.loja_id = ?';
            $params[] = $lojaId;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: null;
    }

    /**
     * Retorna produtos ativos de uma loja especifica com nome + apelidos,
     * usado pelo parser de mensagens do WhatsApp para casar texto livre
     * com um produto — sempre restrito a loja de onde veio a mensagem.
     */
    public static function todosParaCorrespondencia(int $lojaId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT p.id, p.nome, p.apelidos, p.preco_venda, p.quantidade_estoque, c.nome AS categoria_nome
             FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.ativo = 1 AND p.loja_id = ?'
        );
        $stmt->execute([$lojaId]);

        return $stmt->fetchAll();
    }

    public static function criar(array $dados): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO produtos
                (loja_id, nome, apelidos, categoria_id, sku, preco_custo, preco_venda, quantidade_estoque, estoque_minimo, ativo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $dados['loja_id'],
            $dados['nome'],
            $dados['apelidos'] ?? null,
            $dados['categoria_id'] ?: null,
            $dados['sku'] ?? null,
            $dados['preco_custo'],
            $dados['preco_venda'],
            $dados['quantidade_estoque'] ?? 0,
            $dados['estoque_minimo'] ?? 0,
        ]);

        return (int) Database::getConnection()->lastInsertId();
    }

    public static function atualizar(int $id, array $dados): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE produtos SET
                nome = ?, apelidos = ?, categoria_id = ?, sku = ?,
                preco_custo = ?, preco_venda = ?, estoque_minimo = ?, ativo = ?
             WHERE id = ?'
        );

        return $stmt->execute([
            $dados['nome'],
            $dados['apelidos'] ?? null,
            $dados['categoria_id'] ?: null,
            $dados['sku'] ?? null,
            $dados['preco_custo'],
            $dados['preco_venda'],
            $dados['estoque_minimo'] ?? 0,
            $dados['ativo'] ?? 1,
            $id,
        ]);
    }

    public static function ajustarEstoque(int $id, int $delta): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE produtos SET quantidade_estoque = quantidade_estoque + ? WHERE id = ?'
        );

        return $stmt->execute([$delta, $id]);
    }

    public static function comEstoqueBaixo(?int $lojaId = null): array
    {
        $sql = 'SELECT p.*, l.nome AS loja_nome
                FROM produtos p LEFT JOIN lojas l ON l.id = p.loja_id
                WHERE p.ativo = 1 AND p.quantidade_estoque <= p.estoque_minimo';
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' AND p.loja_id = ?';
            $params[] = $lojaId;
        }

        $sql .= ' ORDER BY p.quantidade_estoque ASC';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function categorias(): array
    {
        return Database::getConnection()
            ->query('SELECT id, nome FROM categorias ORDER BY nome')
            ->fetchAll();
    }
}
