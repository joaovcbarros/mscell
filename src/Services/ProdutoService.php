<?php

namespace MsCell\Services;

use MsCell\Models\Produto;

class ProdutoService
{
    /**
     * @return string[] lista de erros (vazia se os dados forem validos)
     */
    public static function validar(array $dados): array
    {
        $erros = [];

        if (trim($dados['nome'] ?? '') === '') {
            $erros[] = 'O nome do produto e obrigatorio.';
        }

        if (!is_numeric($dados['preco_custo'] ?? null) || (float) $dados['preco_custo'] < 0) {
            $erros[] = 'Preco de custo invalido.';
        }

        if (!is_numeric($dados['preco_venda'] ?? null) || (float) $dados['preco_venda'] < 0) {
            $erros[] = 'Preco de venda invalido.';
        }

        if (isset($dados['estoque_minimo']) && (!is_numeric($dados['estoque_minimo']) || (int) $dados['estoque_minimo'] < 0)) {
            $erros[] = 'Estoque minimo invalido.';
        }

        return $erros;
    }

    public static function criar(array $dados): int
    {
        return Produto::criar($dados);
    }

    public static function atualizar(int $id, array $dados): bool
    {
        return Produto::atualizar($id, $dados);
    }
}
