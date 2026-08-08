<?php

namespace MsCell\Services;

use MsCell\Config\Database;
use MsCell\Models\MovimentacaoEstoque;
use MsCell\Models\Produto;
use RuntimeException;

class VendaService
{
    /**
     * Registra uma venda completa dentro de uma transacao: cria o
     * cabecalho, insere os itens, baixa o estoque de cada produto e
     * grava a movimentacao correspondente.
     *
     * @param array<int, array{produto_id:int, quantidade:int, preco_unitario?:float}> $itens
     */
    public static function registrar(
        int $lojaId,
        array $itens,
        string $formaPagamento,
        ?string $clienteNome,
        ?int $usuarioId,
        string $origem = 'sistema',
        ?int $registradoPorId = null
    ): int {
        if (empty($itens)) {
            throw new RuntimeException('A venda precisa ter pelo menos um item.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $valorTotal = 0.0;
            $itensResolvidos = [];

            foreach ($itens as $item) {
                // buscarPorId com $lojaId garante que o produto pertence a
                // essa loja — impede vender item de outra loja mesmo que o
                // produto_id seja manipulado na requisicao.
                $produto = Produto::buscarPorId((int) $item['produto_id'], $lojaId);
                if (!$produto) {
                    throw new RuntimeException('Produto nao encontrado (ID ' . $item['produto_id'] . ').');
                }

                $quantidade = (int) $item['quantidade'];
                if ($quantidade <= 0) {
                    throw new RuntimeException('Quantidade invalida para o produto ' . $produto['nome'] . '.');
                }

                if ($produto['quantidade_estoque'] < $quantidade) {
                    throw new RuntimeException('Estoque insuficiente para ' . $produto['nome'] . '.');
                }

                $precoUnitario = isset($item['preco_unitario'])
                    ? (float) $item['preco_unitario']
                    : (float) $produto['preco_venda'];

                $subtotal = round($precoUnitario * $quantidade, 2);
                $valorTotal += $subtotal;

                $itensResolvidos[] = [
                    'produto' => $produto,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $precoUnitario,
                    'subtotal' => $subtotal,
                ];
            }

            $stmt = $pdo->prepare(
                'INSERT INTO vendas (loja_id, usuario_id, registrado_por_id, cliente_nome, forma_pagamento, valor_total, origem)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$lojaId, $usuarioId, $registradoPorId, $clienteNome ?: null, $formaPagamento, round($valorTotal, 2), $origem]);
            $vendaId = (int) $pdo->lastInsertId();

            $stmtItem = $pdo->prepare(
                'INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario, subtotal)
                 VALUES (?, ?, ?, ?, ?)'
            );

            foreach ($itensResolvidos as $resolvido) {
                $stmtItem->execute([
                    $vendaId,
                    $resolvido['produto']['id'],
                    $resolvido['quantidade'],
                    $resolvido['preco_unitario'],
                    $resolvido['subtotal'],
                ]);

                Produto::ajustarEstoque((int) $resolvido['produto']['id'], -$resolvido['quantidade']);

                MovimentacaoEstoque::registrar(
                    (int) $resolvido['produto']['id'],
                    MovimentacaoEstoque::SAIDA,
                    $resolvido['quantidade'],
                    $origem === 'whatsapp' ? 'Venda automatica via WhatsApp' : 'Venda registrada no sistema',
                    $vendaId,
                    $registradoPorId ?? $usuarioId
                );
            }

            $pdo->commit();

            return $vendaId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
