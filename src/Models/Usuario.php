<?php

namespace MsCell\Models;

use MsCell\Config\Database;
use PDO;

class Usuario
{
    public const PAPEL_ADMIN = 'admin';
    public const PAPEL_FUNCIONARIO = 'funcionario';
    public const PAPEL_USUARIO = 'usuario';

    public static function todos(?int $lojaId = null): array
    {
        $sql = 'SELECT u.id, u.nome, u.email, u.papel, u.loja_id, u.ativo, u.criado_em, l.nome AS loja_nome
                FROM usuarios u LEFT JOIN lojas l ON l.id = u.loja_id';
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' WHERE u.loja_id = ?';
            $params[] = $lojaId;
        }

        $sql .= ' ORDER BY u.nome';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT u.id, u.nome, u.email, u.papel, u.loja_id, u.ativo, u.criado_em, l.nome AS loja_nome
             FROM usuarios u LEFT JOIN lojas l ON l.id = u.loja_id
             WHERE u.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function buscarPorEmail(string $email): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, nome, email, senha_hash, papel, loja_id, ativo FROM usuarios WHERE email = ?'
        );
        $stmt->execute([$email]);

        return $stmt->fetch() ?: null;
    }

    public static function criar(string $nome, string $email, string $senha, string $papel, ?int $lojaId): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO usuarios (nome, email, senha_hash, papel, loja_id) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_BCRYPT), $papel, $lojaId]);

        return (int) Database::getConnection()->lastInsertId();
    }

    public static function atualizar(int $id, string $nome, string $email, string $papel, ?int $lojaId): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE usuarios SET nome = ?, email = ?, papel = ?, loja_id = ? WHERE id = ?'
        );

        return $stmt->execute([$nome, $email, $papel, $lojaId, $id]);
    }

    public static function redefinirSenha(int $id, string $novaSenha): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE usuarios SET senha_hash = ? WHERE id = ?'
        );

        return $stmt->execute([password_hash($novaSenha, PASSWORD_BCRYPT), $id]);
    }

    public static function definirAtivo(int $id, bool $ativo): bool
    {
        $stmt = Database::getConnection()->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?');

        return $stmt->execute([$ativo ? 1 : 0, $id]);
    }

    public static function papeisValidos(): array
    {
        return [self::PAPEL_ADMIN, self::PAPEL_FUNCIONARIO, self::PAPEL_USUARIO];
    }
}
