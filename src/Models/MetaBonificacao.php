<?php

namespace MsCell\Models;

use MsCell\Config\Database;

class MetaBonificacao
{
    /**
     * @param string|null $status 'ativa' ou 'processada'
     * @param string|null $periodoInicio/$periodoFim filtra metas cujo periodo
     *        SOBREPOE o intervalo informado (data_inicio <= periodoFim AND data_fim >= periodoInicio)
     */
    public static function todas(
        ?int $lojaId = null,
        ?string $status = null,
        ?string $periodoInicio = null,
        ?string $periodoFim = null
    ): array {
        $sql = "SELECT m.*, u.nome AS usuario_nome, u.loja_id AS usuario_loja_id, l.nome AS loja_nome
                FROM metas_bonificacao m
                JOIN usuarios u ON u.id = m.usuario_id
                LEFT JOIN lojas l ON l.id = u.loja_id
                WHERE 1=1";
        $params = [];

        if ($lojaId !== null) {
            $sql .= ' AND u.loja_id = ?';
            $params[] = $lojaId;
        }

        if ($status !== null) {
            $sql .= ' AND m.status = ?';
            $params[] = $status;
        }

        if ($periodoInicio !== null && $periodoFim !== null) {
            $sql .= ' AND m.data_inicio <= ? AND m.data_fim >= ?';
            $params[] = $periodoFim;
            $params[] = $periodoInicio;
        }

        $sql .= ' ORDER BY m.data_inicio DESC, u.nome';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT m.*, u.nome AS usuario_nome, u.loja_id AS usuario_loja_id, l.nome AS loja_nome
             FROM metas_bonificacao m
             JOIN usuarios u ON u.id = m.usuario_id
             LEFT JOIN lojas l ON l.id = u.loja_id
             WHERE m.id = ?"
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function criar(array $dados): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO metas_bonificacao
                (usuario_id, data_inicio, data_fim, quantidade_vendas_min, valor_minimo, percentual_bonus)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $dados['usuario_id'],
            $dados['data_inicio'],
            $dados['data_fim'],
            $dados['quantidade_vendas_min'] ?: null,
            $dados['valor_minimo'] ?: null,
            $dados['percentual_bonus'],
        ]);

        return (int) Database::getConnection()->lastInsertId();
    }

    public static function atualizar(int $id, array $dados): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE metas_bonificacao SET
                usuario_id = ?, data_inicio = ?, data_fim = ?,
                quantidade_vendas_min = ?, valor_minimo = ?, percentual_bonus = ?
             WHERE id = ?'
        );

        return $stmt->execute([
            $dados['usuario_id'],
            $dados['data_inicio'],
            $dados['data_fim'],
            $dados['quantidade_vendas_min'] ?: null,
            $dados['valor_minimo'] ?: null,
            $dados['percentual_bonus'],
            $id,
        ]);
    }

    /**
     * So exclui metas 'ativa' — uma meta 'processada' e um registro
     * historico de bonus ja calculado/pago, nao pode ser apagado. A
     * condicao de status entra direto no WHERE por seguranca extra.
     */
    public static function excluir(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "DELETE FROM metas_bonificacao WHERE id = ? AND status = 'ativa'"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Fecha a competencia: congela o resultado do processamento (ver
     * BonificacaoService::processar) e marca a meta como 'processada'.
     */
    public static function marcarProcessada(int $id, array $resultado): void
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE metas_bonificacao SET
                status = 'processada',
                processada_em = NOW(),
                meta_batida = ?,
                percentual_alcancado = ?,
                quantidade_alcancada = ?,
                valor_alcancado = ?,
                salario_base_registrado = ?,
                valor_bonus = ?,
                valor_total_pago = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $resultado['meta_batida'] ? 1 : 0,
            $resultado['percentual_alcancado'],
            $resultado['quantidade_alcancada'],
            $resultado['valor_alcancado'],
            $resultado['salario_base_registrado'],
            $resultado['valor_bonus'],
            $resultado['valor_total_pago'],
            $id,
        ]);
    }

    /**
     * Cria a meta 'ativa' do mes seguinte ao de uma meta recem-processada,
     * clonando funcionario/criterios/percentual e os produtos-alvo.
     */
    public static function criarProximoPeriodo(array $metaProcessada): int
    {
        $fimAtual = new \DateTimeImmutable($metaProcessada['data_fim']);
        $inicioProximo = $fimAtual->modify('+1 day');
        $fimProximo = $inicioProximo->modify('last day of this month');

        $novoId = self::criar([
            'usuario_id' => $metaProcessada['usuario_id'],
            'data_inicio' => $inicioProximo->format('Y-m-d'),
            'data_fim' => $fimProximo->format('Y-m-d'),
            'quantidade_vendas_min' => $metaProcessada['quantidade_vendas_min'],
            'valor_minimo' => $metaProcessada['valor_minimo'],
            'percentual_bonus' => $metaProcessada['percentual_bonus'],
        ]);

        $produtoIds = array_column(self::produtosDaMeta((int) $metaProcessada['id']), 'id');
        if (!empty($produtoIds)) {
            self::definirProdutos($novoId, $produtoIds);
        }

        return $novoId;
    }

    public static function produtosDaMeta(int $metaId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT p.id, p.nome
             FROM metas_bonificacao_produtos mp
             JOIN produtos p ON p.id = mp.produto_id
             WHERE mp.meta_id = ?
             ORDER BY p.nome'
        );
        $stmt->execute([$metaId]);

        return $stmt->fetchAll();
    }

    /**
     * @param int[] $produtoIds
     */
    public static function definirProdutos(int $metaId, array $produtoIds): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM metas_bonificacao_produtos WHERE meta_id = ?');
        $stmt->execute([$metaId]);

        if (empty($produtoIds)) {
            return;
        }

        $stmtInsert = $pdo->prepare(
            'INSERT INTO metas_bonificacao_produtos (meta_id, produto_id) VALUES (?, ?)'
        );

        foreach (array_unique($produtoIds) as $produtoId) {
            $stmtInsert->execute([$metaId, (int) $produtoId]);
        }
    }
}
