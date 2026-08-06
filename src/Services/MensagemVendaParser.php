<?php

namespace MsCell\Services;

use MsCell\Models\Produto;

/**
 * Interpreta mensagens de texto livre vindas do WhatsApp, do tipo
 * "vendi iphone 15 pro max 5.000" ou "venda: fone bluetooth 100",
 * tentando extrair produto, quantidade e valor da venda.
 *
 * Formato recomendado (documentado em docs/WHATSAPP.md) para maior
 * taxa de acerto: "vendi: <produto>, <valor>". Mas o parser tambem
 * tenta cobrir variacoes mais soltas via regex + correspondencia
 * aproximada de nome/apelido de produto.
 */
class MensagemVendaParser
{
    /** Abaixo disso, a venda NAO e cadastrada automaticamente. */
    public const CONFIANCA_MINIMA_AUTOMATICA = 0.62;

    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    private const PREFIXOS_VENDA = '/^(vendi|vendemos|vendeu|venda)\b\s*:?\s*/u';
    private const PALAVRAS_RUIDO = '/\b(por|reais|real|rs|r\$)\b/u';

    /**
     * Casa um valor monetario. A primeira alternativa exige pelo menos um
     * grupo ".ddd" (separador de milhar) para so entrar em jogo quando ele
     * realmente existir; a segunda casa um numero simples completo (sem
     * limite de digitos) opcionalmente seguido de centavos. Sem essa
     * distincao, um numero solto de 4+ digitos (ex: "5950") seria cortado
     * em pedacos de 3 em 3 pela alternativa de milhar.
     */
    private const REGEX_VALOR = '/(?:r\$\s*)?(\d{1,3}(?:\.\d{3})+(?:,\d{2})?|\d+(?:[.,]\d{1,2})?)/u';

    public static function interpretar(string $mensagemBruta, int $lojaId): array
    {
        $normalizada = self::normalizar($mensagemBruta);
        $semPrefixo = trim(preg_replace(self::PREFIXOS_VENDA, '', $normalizada) ?? $normalizada);

        $valor = self::extrairValor($semPrefixo);
        [$quantidade, $semQuantidade] = self::extrairQuantidade($semPrefixo);

        $textoProduto = self::extrairTextoProduto($semQuantidade, $valor);

        [$produto, $confianca] = self::casarProduto($textoProduto, $lojaId);

        return [
            'mensagem_bruta' => $mensagemBruta,
            'mensagem_normalizada' => $normalizada,
            'texto_produto_extraido' => $textoProduto,
            'quantidade' => $quantidade,
            'valor' => $valor,
            'produto_id' => $produto['id'] ?? null,
            'produto_nome' => $produto['nome'] ?? null,
            'categoria_nome' => $produto['categoria_nome'] ?? null,
            'confianca_produto' => $confianca,
            'reconhecido' => $produto !== null
                && $valor !== null
                && $confianca >= self::CONFIANCA_MINIMA_AUTOMATICA,
        ];
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, self::ACENTOS);
        $texto = preg_replace('/[^\p{L}\p{N}\s.,\$]/u', ' ', $texto) ?? $texto;

        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }

    private static function extrairValor(string $texto): ?float
    {
        if (!preg_match_all(self::REGEX_VALOR, $texto, $matches)) {
            return null;
        }

        $bruto = end($matches[1]);

        return $bruto === false ? null : self::converterNumero($bruto);
    }

    private static function converterNumero(string $bruto): float
    {
        $temPonto = str_contains($bruto, '.');
        $temVirgula = str_contains($bruto, ',');

        if ($temPonto && $temVirgula) {
            return (float) str_replace(',', '.', str_replace('.', '', $bruto));
        }

        if ($temVirgula) {
            $partes = explode(',', $bruto);
            if (strlen(end($partes)) === 2) {
                return (float) str_replace(',', '.', $bruto);
            }

            return (float) str_replace(',', '', $bruto);
        }

        if ($temPonto) {
            $partes = explode('.', $bruto);
            if (strlen(end($partes)) === 3) {
                return (float) str_replace('.', '', $bruto);
            }

            return (float) $bruto;
        }

        return (float) $bruto;
    }

    /**
     * @return array{0:int,1:string} quantidade detectada + texto restante
     */
    private static function extrairQuantidade(string $texto): array
    {
        if (preg_match('/^(\d{1,2})\s*x\s+/u', $texto, $m)) {
            return [(int) $m[1], trim(substr($texto, strlen($m[0])))];
        }

        return [1, $texto];
    }

    private static function extrairTextoProduto(string $texto, ?float $valor): string
    {
        $texto = preg_replace(self::PREFIXOS_VENDA, '', $texto) ?? $texto;

        if ($valor !== null) {
            // Remove a ultima ocorrencia de um numero (o valor) do texto.
            $pos = null;
            if (preg_match_all(self::REGEX_VALOR, $texto, $m, PREG_OFFSET_CAPTURE)) {
                $ultimo = end($m[0]);
                if ($ultimo !== false) {
                    $pos = $ultimo[1];
                    $texto = substr($texto, 0, $pos) . substr($texto, $pos + strlen($ultimo[0]));
                }
            }
        }

        $texto = preg_replace(self::PALAVRAS_RUIDO, ' ', $texto) ?? $texto;
        $texto = str_replace([':', ','], ' ', $texto);

        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }

    /**
     * @return array{0:?array,1:float}
     */
    private static function casarProduto(string $textoProduto, int $lojaId): array
    {
        if ($textoProduto === '') {
            return [null, 0.0];
        }

        $melhorProduto = null;
        $melhorScore = 0.0;

        foreach (Produto::todosParaCorrespondencia($lojaId) as $produto) {
            $candidatos = array_merge(
                [$produto['nome']],
                $produto['apelidos'] ? explode('|', $produto['apelidos']) : []
            );

            foreach ($candidatos as $candidato) {
                $score = self::similaridade($textoProduto, self::normalizar($candidato));
                if ($score > $melhorScore) {
                    $melhorScore = $score;
                    $melhorProduto = $produto;
                }
            }
        }

        return [$melhorProduto, round($melhorScore, 2)];
    }

    private static function similaridade(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        // Numeros (modelo) divergentes = produtos diferentes, mesmo que o
        // resto do texto seja parecido (ex: "12 pro max" nao pode virar
        // "iPhone 15 Pro Max" so porque "pro max" bate).
        if (self::numerosConflitam($a, $b)) {
            return 0.0;
        }

        if (str_contains($a, $b) || str_contains($b, $a)) {
            $maior = max(strlen($a), strlen($b));
            $menor = min(strlen($a), strlen($b));

            return 0.75 + 0.25 * ($menor / $maior);
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    /**
     * Verdadeiro quando os dois textos tem numeros e nenhum deles bate
     * (ex: "12" em um lado e "15" no outro) — sinal forte de modelos
     * diferentes (iPhone 12 vs iPhone 15, etc).
     */
    private static function numerosConflitam(string $a, string $b): bool
    {
        preg_match_all('/\d+/', $a, $numerosA);
        preg_match_all('/\d+/', $b, $numerosB);

        $numerosA = $numerosA[0];
        $numerosB = $numerosB[0];

        if (empty($numerosA) || empty($numerosB)) {
            return false;
        }

        return empty(array_intersect($numerosA, $numerosB));
    }
}
