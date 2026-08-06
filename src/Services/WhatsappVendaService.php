<?php

namespace MsCell\Services;

use MsCell\Models\MensagemWhatsapp;
use MsCell\Models\PendenciaWhatsapp;
use MsCell\Models\Produto;

/**
 * Orquestra o fluxo completo de uma mensagem recebida do WhatsApp:
 * interpreta o texto, tenta cadastrar a venda automaticamente e
 * registra tudo em whatsapp_mensagens_log para auditoria/revisao.
 *
 * Quando o parser acha um valor mas nao reconhece o produto, o sistema
 * pergunta se deve cadastrar um produto novo e aguarda a confirmacao na
 * proxima mensagem do mesmo numero (ver whatsapp_pendencias).
 */
class WhatsappVendaService
{
    private const EMOJI_POR_CATEGORIA = [
        'celulares' => '📱',
        'acessórios' => '🎧',
        'acessorios' => '🎧',
        'peças' => '🔧',
        'pecas' => '🔧',
        'serviços' => '🛠️',
        'servicos' => '🛠️',
    ];

    private const EMOJI_PADRAO = '🛍️';

    private const RESPOSTAS_AFIRMATIVAS = ['sim', 's', 'yes', 'confirmar', 'confirmo', 'pode', 'ok'];
    private const RESPOSTAS_NEGATIVAS = ['nao', 'n', 'no', 'cancelar', 'cancela'];

    public static function processarMensagem(int $lojaId, string $numeroOrigem, string $mensagemBruta): array
    {
        $pendencia = PendenciaWhatsapp::buscarAguardando($numeroOrigem);

        if ($pendencia !== null) {
            $resultado = self::tratarRespostaPendencia($pendencia, $mensagemBruta);
            if ($resultado !== null) {
                return $resultado;
            }
            // null = resposta nao foi um sim/nao claro; a pendencia foi
            // expirada e a mensagem segue o fluxo normal abaixo.
        }

        $interpretacao = MensagemVendaParser::interpretar($mensagemBruta, $lojaId);

        if ($interpretacao['reconhecido']) {
            return self::registrarVendaReconhecida($lojaId, $numeroOrigem, $mensagemBruta, $interpretacao);
        }

        // Achou um valor e um texto de produto, mas nao reconheceu nenhum
        // produto cadastrado: oferece cadastrar como produto novo.
        if ($interpretacao['valor'] !== null && $interpretacao['texto_produto_extraido'] !== '') {
            MensagemWhatsapp::registrar($numeroOrigem, $lojaId, $mensagemBruta, $interpretacao, null, 'revisao');

            PendenciaWhatsapp::criar(
                $numeroOrigem,
                $lojaId,
                $interpretacao['texto_produto_extraido'],
                $interpretacao['valor'],
                $interpretacao['quantidade']
            );

            return [
                'sucesso' => false,
                'resposta' => sprintf(
                    "🤔 Não encontrei \"%s\" cadastrado. Quer que eu cadastre esse produto novo com preço de venda R$ %s e já registre essa venda? Responda *sim* para confirmar ou *não* para cancelar.",
                    $interpretacao['texto_produto_extraido'],
                    number_format($interpretacao['valor'], 2, ',', '.')
                ),
                'interpretacao' => $interpretacao,
            ];
        }

        MensagemWhatsapp::registrar($numeroOrigem, $lojaId, $mensagemBruta, $interpretacao, null, 'revisao');

        return [
            'sucesso' => false,
            'resposta' => self::mensagemPedidoDeDetalhes($interpretacao),
            'interpretacao' => $interpretacao,
        ];
    }

    private static function registrarVendaReconhecida(int $lojaId, string $numeroOrigem, string $mensagemBruta, array $interpretacao): array
    {
        try {
            $vendaId = VendaService::registrar(
                lojaId: $lojaId,
                itens: [[
                    'produto_id' => $interpretacao['produto_id'],
                    'quantidade' => $interpretacao['quantidade'],
                    'preco_unitario' => $interpretacao['valor'] / max(1, $interpretacao['quantidade']),
                ]],
                formaPagamento: 'outro',
                clienteNome: null,
                usuarioId: null,
                origem: 'whatsapp'
            );

            MensagemWhatsapp::registrar($numeroOrigem, $lojaId, $mensagemBruta, $interpretacao, $vendaId, 'processada');

            return [
                'sucesso' => true,
                'resposta' => sprintf(
                    "✅ Venda registrada: %s %s (qtd %d) - R$ %s",
                    self::emojiDaCategoria($interpretacao['categoria_nome'] ?? null),
                    $interpretacao['produto_nome'],
                    $interpretacao['quantidade'],
                    number_format($interpretacao['valor'], 2, ',', '.')
                ),
                'venda_id' => $vendaId,
                'interpretacao' => $interpretacao,
            ];
        } catch (\Throwable $e) {
            MensagemWhatsapp::registrar($numeroOrigem, $lojaId, $mensagemBruta, $interpretacao, null, 'falha');

            return [
                'sucesso' => false,
                'resposta' => '⚠️ Entendi o produto, mas nao consegui registrar a venda: ' . $e->getMessage(),
                'interpretacao' => $interpretacao,
            ];
        }
    }

    /**
     * @return array|null null significa "nao foi uma resposta sim/nao
     * clara" — a pendencia ja foi marcada como expirada nesse caso, e o
     * chamador deve processar a mensagem normalmente.
     */
    private static function tratarRespostaPendencia(array $pendencia, string $mensagemBruta): ?array
    {
        $resposta = self::normalizarResposta($mensagemBruta);

        if (in_array($resposta, self::RESPOSTAS_AFIRMATIVAS, true)) {
            return self::confirmarNovoProduto($pendencia);
        }

        if (in_array($resposta, self::RESPOSTAS_NEGATIVAS, true)) {
            PendenciaWhatsapp::marcarStatus((int) $pendencia['id'], 'cancelada');

            return [
                'sucesso' => false,
                'resposta' => 'Ok, cancelado. Se quiser, cadastre esse produto manualmente pelo sistema.',
            ];
        }

        PendenciaWhatsapp::marcarStatus((int) $pendencia['id'], 'expirada');

        return null;
    }

    private static function confirmarNovoProduto(array $pendencia): array
    {
        PendenciaWhatsapp::marcarStatus((int) $pendencia['id'], 'confirmada');

        $nomeProduto = mb_convert_case($pendencia['texto_produto'], MB_CASE_TITLE, 'UTF-8');
        $quantidade = (int) $pendencia['quantidade'];
        $valor = (float) $pendencia['valor'];

        try {
            $produtoId = Produto::criar([
                'loja_id' => (int) $pendencia['loja_id'],
                'nome' => $nomeProduto,
                'apelidos' => null,
                'categoria_id' => null,
                'sku' => null,
                'preco_custo' => 0,
                'preco_venda' => $valor,
                'quantidade_estoque' => $quantidade,
                'estoque_minimo' => 0,
            ]);

            $vendaId = VendaService::registrar(
                lojaId: (int) $pendencia['loja_id'],
                itens: [[
                    'produto_id' => $produtoId,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $valor / max(1, $quantidade),
                ]],
                formaPagamento: 'outro',
                clienteNome: null,
                usuarioId: null,
                origem: 'whatsapp'
            );

            return [
                'sucesso' => true,
                'resposta' => sprintf(
                    "✅ Produto \"%s\" cadastrado e venda registrada - R$ %s. Complete categoria, preço de custo e estoque mínimo pelo sistema quando puder.",
                    $nomeProduto,
                    number_format($valor, 2, ',', '.')
                ),
                'venda_id' => $vendaId,
                'produto_id' => $produtoId,
            ];
        } catch (\Throwable $e) {
            return [
                'sucesso' => false,
                'resposta' => '⚠️ Não consegui cadastrar o produto: ' . $e->getMessage(),
            ];
        }
    }

    private static function normalizarResposta(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');

        return strtr($texto, [
            'á' => 'a', 'ã' => 'a', 'â' => 'a', 'à' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ç' => 'c',
        ]);
    }

    private static function emojiDaCategoria(?string $categoriaNome): string
    {
        if ($categoriaNome === null) {
            return self::EMOJI_PADRAO;
        }

        $chave = mb_strtolower($categoriaNome, 'UTF-8');

        return self::EMOJI_POR_CATEGORIA[$chave] ?? self::EMOJI_PADRAO;
    }

    private static function mensagemPedidoDeDetalhes(array $interpretacao): string
    {
        if ($interpretacao['valor'] === null) {
            return "Nao consegui identificar o valor da venda. Tente algo como: \"vendi iphone 15 pro max 5000\".";
        }

        if ($interpretacao['produto_id'] === null) {
            return "Nao consegui identificar o produto. Confira o nome cadastrado no sistema e tente novamente.";
        }

        return "Nao tenho certeza dessa venda, ela ficou pendente de revisao no sistema.";
    }
}
