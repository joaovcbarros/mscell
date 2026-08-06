<?php

namespace MsCell\Services;

use MsCell\Models\Usuario;

class AuthService
{
    public static function login(string $email, string $senha): bool
    {
        $usuario = Usuario::buscarPorEmail($email);

        if (!$usuario || !$usuario['ativo'] || !password_verify($senha, $usuario['senha_hash'])) {
            return false;
        }

        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_papel'] = $usuario['papel'];
        $_SESSION['usuario_loja_id'] = $usuario['loja_id'] !== null ? (int) $usuario['loja_id'] : null;
        unset($_SESSION['loja_filtro']); // admin comeca sempre vendo "todas as lojas"

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function usuarioLogado(): bool
    {
        return isset($_SESSION['usuario_id']);
    }

    public static function papelAtual(): ?string
    {
        return $_SESSION['usuario_papel'] ?? null;
    }

    public static function idAtual(): ?int
    {
        return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    }

    /**
     * Loja a qual o usuario logado esta vinculado. Null para admin
     * (que nao tem uma loja fixa — enxerga todas).
     */
    public static function lojaId(): ?int
    {
        return $_SESSION['usuario_loja_id'] ?? null;
    }

    public static function podeVerTodasLojas(): bool
    {
        return self::papelAtual() === Usuario::PAPEL_ADMIN;
    }

    /**
     * Loja que deve ser usada para filtrar as consultas nesta requisicao:
     * - funcionario/usuario: sempre a propria loja (nao pode ser burlado)
     * - admin: a loja escolhida no seletor do topo (null = "todas as lojas")
     */
    public static function lojaEfetiva(): ?int
    {
        if (!self::podeVerTodasLojas()) {
            return self::lojaId();
        }

        return $_SESSION['loja_filtro'] ?? null;
    }

    /**
     * Define a loja escolhida no seletor do topo (somente admin).
     */
    public static function definirLojaFiltro(?int $lojaId): void
    {
        $_SESSION['loja_filtro'] = $lojaId;
    }

    /**
     * Redireciona para o login se nao houver sessao ativa.
     */
    public static function exigirLogin(): void
    {
        if (!self::usuarioLogado()) {
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * Redireciona para o dashboard (com aviso) se o papel atual nao
     * estiver entre os permitidos para a pagina.
     */
    public static function exigirPapel(array $papeisPermitidos): void
    {
        self::exigirLogin();

        if (!in_array(self::papelAtual(), $papeisPermitidos, true)) {
            header('Location: /dashboard.php?erro=acesso_negado');
            exit;
        }
    }
}
