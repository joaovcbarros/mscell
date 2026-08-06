<?php

namespace MsCell\Services;

use MsCell\Config\Database;
use MsCell\Models\MovimentacaoEstoque;
use MsCell\Models\Produto;
use RuntimeException;

class EstoqueService
{
    /**
     * Ajuste manual de estoque feito por um usuario (entrada por compra,
     * correcao de inventario, etc). Quantidade sempre positiva; o tipo
     * define se soma ou subtrai do estoque atual.
     */
    public static function ajustarManualmente(
        int $produtoId,
        string $tipo,
        int $quantidade,
        string $motivo,
        int $usuarioId,
        ?int $lojaId = null
    ): void {
        if ($quantidade <= 0) {
            throw new RuntimeException('A quantidade deve ser maior que zero.');
        }

        $produto = Produto::buscarPorId($produtoId, $lojaId);
        if (!$produto) {
            throw new RuntimeException('Produto nao encontrado.');
        }

        $delta = match ($tipo) {
            MovimentacaoEstoque::ENTRADA => $quantidade,
            MovimentacaoEstoque::SAIDA => -$quantidade,
            MovimentacaoEstoque::AJUSTE => $quantidade,
            default => throw new RuntimeException('Tipo de movimentacao invalido.'),
        };

        if ($tipo === MovimentacaoEstoque::SAIDA && $produto['quantidade_estoque'] + $delta < 0) {
            throw new RuntimeException('Estoque insuficiente para essa saida.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            Produto::ajustarEstoque($produtoId, $delta);
            MovimentacaoEstoque::registrar($produtoId, $tipo, $quantidade, $motivo, null, $usuarioId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
