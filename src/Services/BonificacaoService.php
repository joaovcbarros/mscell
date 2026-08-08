<?php

namespace MsCell\Services;

use MsCell\Config\Database;
use MsCell\Models\MetaBonificacao;
use MsCell\Models\Usuario;

/**
 * Calcula o progresso de uma meta de bonificacao: quantas vendas e quanto
 * em valor o funcionario alcancou no periodo da meta, se bateu os
 * criterios definidos pelo admin, e o resultado final (salario + bonus)
 * quando a competencia e processada/fechada.
 */
class BonificacaoService
{
    /**
     * @return array{quantidade_alcancada:int, valor_alcancado:float, atingida:bool}
     */
    public static function avaliar(array $meta): array
    {
        [$quantidadeAlcancada, $valorAlcancado] = self::totais($meta);

        $atingida = self::calcularPercentualAlcancado($meta, $quantidadeAlcancada, $valorAlcancado) >= 100.0;

        return [
            'quantidade_alcancada' => $quantidadeAlcancada,
            'valor_alcancado' => $valorAlcancado,
            'atingida' => $atingida,
        ];
    }

    /**
     * Congela o resultado final da meta pra fins de processamento
     * (fechamento de competencia). Nao grava nada no banco — quem chama
     * decide o que fazer com o resultado (ver MetaBonificacao::marcarProcessada).
     *
     * @return array{quantidade_alcancada:int, valor_alcancado:float, percentual_alcancado:float,
     *               meta_batida:bool, salario_base_registrado:float, valor_bonus:float, valor_total_pago:float}
     */
    public static function processar(array $meta): array
    {
        [$quantidadeAlcancada, $valorAlcancado] = self::totais($meta);
        $percentualAlcancado = self::calcularPercentualAlcancado($meta, $quantidadeAlcancada, $valorAlcancado);
        $metaBatida = $percentualAlcancado >= 100.0;

        $usuario = Usuario::buscarPorId((int) $meta['usuario_id']);
        $salarioBase = (float) ($usuario['salario_base'] ?? 0);

        $valorBonus = $metaBatida
            ? round($salarioBase * ((float) $meta['percentual_bonus'] / 100), 2)
            : 0.0;

        return [
            'quantidade_alcancada' => $quantidadeAlcancada,
            'valor_alcancado' => $valorAlcancado,
            'percentual_alcancado' => $percentualAlcancado,
            'meta_batida' => $metaBatida,
            'salario_base_registrado' => $salarioBase,
            'valor_bonus' => $valorBonus,
            'valor_total_pago' => round($salarioBase + $valorBonus, 2),
        ];
    }

    /**
     * Quanto o funcionario chegou perto da meta, em 0-100. Quando ha mais
     * de um criterio definido (quantidade E valor), usa o mais fraco dos
     * dois — só bate 100% quando TODOS os criterios definidos batem.
     */
    public static function calcularPercentualAlcancado(array $meta, int $quantidadeAlcancada, float $valorAlcancado): float
    {
        $razoes = [];

        if ($meta['quantidade_vendas_min'] !== null && (int) $meta['quantidade_vendas_min'] > 0) {
            $razoes[] = min(1, $quantidadeAlcancada / (int) $meta['quantidade_vendas_min']);
        }

        if ($meta['valor_minimo'] !== null && (float) $meta['valor_minimo'] > 0) {
            $razoes[] = min(1, $valorAlcancado / (float) $meta['valor_minimo']);
        }

        if (empty($razoes)) {
            return 0.0;
        }

        return round(min($razoes) * 100, 2);
    }

    /**
     * Vendas que contam pra meta, pra exibir no relatorio de conferencia.
     */
    public static function itensContabilizados(array $meta): array
    {
        $produtoIds = array_column(MetaBonificacao::produtosDaMeta((int) $meta['id']), 'id');

        $sql = "SELECT v.id AS venda_id, v.criado_em, v.cliente_nome, iv.quantidade, iv.preco_unitario, iv.subtotal, p.nome AS produto_nome
                FROM vendas v
                JOIN itens_venda iv ON iv.venda_id = v.id
                JOIN produtos p ON p.id = iv.produto_id
                WHERE v.usuario_id = ? AND v.status = 'concluida'
                  AND v.criado_em >= ? AND v.criado_em <= ?";
        $params = [
            $meta['usuario_id'],
            $meta['data_inicio'] . ' 00:00:00',
            $meta['data_fim'] . ' 23:59:59',
        ];

        if (!empty($produtoIds)) {
            $marcadores = implode(',', array_fill(0, count($produtoIds), '?'));
            $sql .= " AND iv.produto_id IN ($marcadores)";
            $params = [...$params, ...$produtoIds];
        }

        $sql .= ' ORDER BY v.criado_em';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return array{0:int,1:float}
     */
    private static function totais(array $meta): array
    {
        $produtoIds = array_column(MetaBonificacao::produtosDaMeta((int) $meta['id']), 'id');

        return empty($produtoIds)
            ? self::totalGeral($meta)
            : self::totalPorProdutos($meta, $produtoIds);
    }

    /**
     * @return array{0:int,1:float}
     */
    private static function totalGeral(array $meta): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total), 0) AS valor
             FROM vendas
             WHERE usuario_id = ? AND status = 'concluida'
               AND criado_em >= ? AND criado_em <= ?"
        );
        $stmt->execute([
            $meta['usuario_id'],
            $meta['data_inicio'] . ' 00:00:00',
            $meta['data_fim'] . ' 23:59:59',
        ]);
        $linha = $stmt->fetch();

        return [(int) $linha['qtd'], (float) $linha['valor']];
    }

    /**
     * @param int[] $produtoIds
     * @return array{0:int,1:float}
     */
    private static function totalPorProdutos(array $meta, array $produtoIds): array
    {
        $marcadores = implode(',', array_fill(0, count($produtoIds), '?'));

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(DISTINCT v.id) AS qtd, COALESCE(SUM(iv.subtotal), 0) AS valor
             FROM vendas v
             JOIN itens_venda iv ON iv.venda_id = v.id
             WHERE v.usuario_id = ? AND v.status = 'concluida'
               AND v.criado_em >= ? AND v.criado_em <= ?
               AND iv.produto_id IN ($marcadores)"
        );
        $stmt->execute([
            $meta['usuario_id'],
            $meta['data_inicio'] . ' 00:00:00',
            $meta['data_fim'] . ' 23:59:59',
            ...$produtoIds,
        ]);
        $linha = $stmt->fetch();

        return [(int) $linha['qtd'], (float) $linha['valor']];
    }
}
