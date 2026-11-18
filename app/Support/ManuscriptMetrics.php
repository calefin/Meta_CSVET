<?php

namespace App\Support;

/**
 * Estimativa das medidas do manuscrito a partir dos parágrafos do .docx/.odt
 * (DocxInspector): palavras do texto principal (sem resumo, referências e
 * legendas), palavras do resumo, nº de referências e de figuras/tabelas.
 * É heurística — depende de títulos como "Abstract", "References".
 */
class ManuscriptMetrics
{
    protected const ABSTRACT = '/^(abstract|resumo|summary)\b\s*[:.\-–]?\s*/iu';
    protected const ABSTRACT_END = '/^(key\s*words|keywords|palavras[\s-]chave|introduction|introdução|introducao|background)\b/iu';
    protected const REFERENCES = '/^(references|referências|referencias|referências bibliográficas|bibliography|literature cited)\s*:?$/iu';
    protected const AFTER_REFERENCES = '/^(tables?|tabelas?|figure legends|legendas|supplementary|material suplementar|appendix|anexos?)\b/iu';
    protected const FIGURE = '/^(?:fig(?:ure|ura)?\.?)\s*(\d+)/iu';
    protected const TABLE = '/^(?:table|tabela|quadro)\s*(\d+)/iu';

    /**
     * @param array<int, string> $paragraphs
     * @return array{text_words: int, abstract_words: ?int, references: ?int, figures: int, tables: int, found_abstract: bool, found_references: bool}
     */
    public static function fromParagraphs(array $paragraphs): array
    {
        $abstractStart = null;
        $abstractEnd = null;
        $refStart = null;
        $refEnd = count($paragraphs);
        $figures = [];
        $tables = [];

        foreach ($paragraphs as $i => $p) {
            if ($abstractStart === null && $refStart === null && preg_match(self::ABSTRACT, $p) && mb_strlen($p) < 3000) {
                $abstractStart = $i;
            } elseif ($abstractStart !== null && $abstractEnd === null && preg_match(self::ABSTRACT_END, $p)) {
                $abstractEnd = $i;
            }
            if ($refStart === null && preg_match(self::REFERENCES, $p)) {
                $refStart = $i;
            } elseif ($refStart !== null && $refEnd === count($paragraphs) && preg_match(self::AFTER_REFERENCES, $p) && mb_strlen($p) < 80) {
                $refEnd = $i;
            }
            if (preg_match(self::FIGURE, $p, $m)) {
                $figures[(int) $m[1]] = true;
            } elseif (preg_match(self::TABLE, $p, $m)) {
                $tables[(int) $m[1]] = true;
            }
        }

        $abstractWords = null;
        if ($abstractStart !== null) {
            // Sem "Keywords/Introduction" depois: só o próprio parágrafo (se o texto
            // veio junto do título "Abstract:") ou o parágrafo seguinte.
            $inline = self::words(preg_replace(self::ABSTRACT, '', $paragraphs[$abstractStart])) > 20;
            $abstractEnd ??= min($abstractStart + ($inline ? 1 : 2), count($paragraphs));
            $abstractWords = 0;
            for ($i = $abstractStart; $i < $abstractEnd; $i++) {
                $text = $i === $abstractStart ? preg_replace(self::ABSTRACT, '', $paragraphs[$i]) : $paragraphs[$i];
                $abstractWords += self::words($text);
            }
        }

        $textStart = $abstractEnd ?? 0;
        if ($abstractEnd !== null && preg_match('/^(key\s*words|keywords|palavras[\s-]chave)\b/iu', $paragraphs[$abstractEnd] ?? '')) {
            $textStart++;
        }
        $textEnd = $refStart ?? count($paragraphs);
        $textWords = 0;
        for ($i = $textStart; $i < $textEnd; $i++) {
            if (preg_match(self::FIGURE, $paragraphs[$i]) || preg_match(self::TABLE, $paragraphs[$i])) {
                continue;
            }
            $textWords += self::words($paragraphs[$i]);
        }

        $references = null;
        if ($refStart !== null) {
            $references = 0;
            for ($i = $refStart + 1; $i < $refEnd; $i++) {
                if (mb_strlen(trim($paragraphs[$i])) >= 20 && !preg_match(self::FIGURE, $paragraphs[$i]) && !preg_match(self::TABLE, $paragraphs[$i])) {
                    $references++;
                }
            }
        }

        return [
            'text_words' => $textWords,
            'abstract_words' => $abstractWords,
            'references' => $references,
            'figures' => count($figures),
            'tables' => count($tables),
            'found_abstract' => $abstractStart !== null,
            'found_references' => $refStart !== null,
        ];
    }

    protected static function words(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', $text);
    }
}
