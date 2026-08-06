<?php

namespace MsCell\Models;

use MsCell\Config\Database;
use PDOException;
use RuntimeException;

class Loja
{
    public static function todas(bool $apenasAtivas = false): array
    {
        $sql = 'SELECT * FROM lojas';

        if ($apenasAtivas) {
            $sql .= ' WHERE ativa = 1';
        }

        $sql .= ' ORDER BY nome';

        return Database::getConnection()->query($sql)->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM lojas WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function buscarPorNumeroWhatsapp(string $numero): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM lojas WHERE numero_whatsapp = ? AND ativa = 1'
        );
        $stmt->execute([$numero]);

        return $stmt->fetch() ?: null;
    }

    public static function criar(string $nome, ?string $endereco, ?string $numeroWhatsapp): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO lojas (nome, endereco, numero_whatsapp) VALUES (?, ?, ?)'
        );
        $stmt->execute([$nome, $endereco ?: null, $numeroWhatsapp ?: null]);

        return (int) Database::getConnection()->lastInsertId();
    }

    public static function atualizar(int $id, string $nome, ?string $endereco, ?string $numeroWhatsapp, bool $ativa): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE lojas SET nome = ?, endereco = ?, numero_whatsapp = ?, ativa = ? WHERE id = ?'
        );

        return $stmt->execute([$nome, $endereco ?: null, $numeroWhatsapp ?: null, $ativa ? 1 : 0, $id]);
    }

    /**
     * So permite excluir uma loja que nao tem nenhum dado vinculado
     * (produtos, vendas, usuarios, mensagens de WhatsApp). Isso protege
     * o historico — uma loja com movimento deve ser desativada, nao
     * excluida. Lanca RuntimeException com uma mensagem amigavel quando
     * a exclusao nao e possivel.
     */
    public static function excluir(int $id): void
    {
        try {
            $stmt = Database::getConnection()->prepare('DELETE FROM lojas WHERE id = ?');
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'foreign key constraint')) {
                throw new RuntimeException(
                    'Essa loja tem produtos, vendas, usuários ou mensagens vinculados e não pode ser excluída. Desative-a em vez disso.'
                );
            }

            throw $e;
        }
    }
}
