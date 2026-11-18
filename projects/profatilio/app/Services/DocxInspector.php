<?php

namespace App\Services;

/**
 * Lê o texto de um .docx/.odt (sem executar nada) e compara duas versões:
 * marcas de "Controlar Alterações", comentários, parágrafos/palavras
 * alterados e semelhança — para acusar envios errados (arquivo idêntico,
 * documento de outro trabalho, alterações sem controle).
 */
class DocxInspector
{
    /**
     * @return array{paragraphs: array<int, string>, insertions: int, deletions: int, comments: int, words: int, readable: bool}
     */
    public static function analyze(string $content, string $extension): array
    {
        $empty = ['paragraphs' => [], 'insertions' => 0, 'deletions' => 0, 'comments' => 0, 'words' => 0, 'readable' => false];

        if (!class_exists(\ZipArchive::class)) {
            return $empty;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'dxi');
        file_put_contents($tmp, $content);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp, \ZipArchive::RDONLY) !== true) {
                return $empty;
            }

            if (strtolower($extension) === 'odt') {
                $xml = (string) $zip->getFromName('content.xml');
                $zip->close();

                return self::analyzeOdt($xml);
            }

            $xml = (string) $zip->getFromName('word/document.xml');
            $commentsXml = (string) $zip->getFromName('word/comments.xml');
            $zip->close();

            return self::analyzeDocx($xml, $commentsXml);
        } catch (\Throwable) {
            return $empty;
        } finally {
            @unlink($tmp);
        }
    }

    protected static function analyzeDocx(string $xml, string $commentsXml): array
    {
        $insertions = preg_match_all('/<w:ins\b/', $xml);
        $deletions = preg_match_all('/<w:del\b/', $xml);
        $comments = preg_match_all('/<w:comment\b/', $commentsXml);

        // Texto "como se todas as alterações fossem aceitas": tira o que foi excluído.
        $clean = preg_replace('/<w:del\b[^>]*>.*?<\/w:del>/s', '', $xml) ?? $xml;
        $clean = preg_replace('/<w:delText\b[^>]*>.*?<\/w:delText>/s', '', $clean) ?? $clean;

        $paragraphs = [];
        if (preg_match_all('/<w:p[ >].*?<\/w:p>/s', $clean, $m)) {
            foreach ($m[0] as $p) {
                $p = preg_replace('/<w:tab\/>/', ' ', $p);
                preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $p, $t);
                $paragraphs[] = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        return self::finish($paragraphs, $insertions, $deletions, $comments);
    }

    protected static function analyzeOdt(string $xml): array
    {
        $insertions = preg_match_all('/<text:insertion\b/', $xml);
        $deletions = preg_match_all('/<text:deletion\b/', $xml);
        $comments = preg_match_all('/<office:annotation\b/', $xml);

        $clean = preg_replace('/<text:tracked-changes\b.*?<\/text:tracked-changes>/s', '', $xml) ?? $xml;
        $clean = preg_replace('/<office:annotation\b.*?<\/office:annotation>/s', '', $clean) ?? $clean;

        $paragraphs = [];
        if (preg_match_all('/<text:(p|h)\b[^>]*>(.*?)<\/text:\1>/s', $clean, $m)) {
            foreach ($m[2] as $p) {
                $p = preg_replace('/<text:(tab|s)\b[^>]*\/>/', ' ', $p);
                $paragraphs[] = html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        return self::finish($paragraphs, $insertions, $deletions, $comments);
    }

    protected static function finish(array $paragraphs, int $ins, int $del, int $comments): array
    {
        $paragraphs = array_values(array_filter(array_map(
            fn ($p) => trim(preg_replace('/\s+/u', ' ', $p)),
            $paragraphs
        ), fn ($p) => $p !== ''));

        $words = 0;
        foreach ($paragraphs as $p) {
            $words += count(self::words($p));
        }

        return [
            'paragraphs' => $paragraphs,
            'insertions' => $ins,
            'deletions' => $del,
            'comments' => $comments,
            'words' => $words,
            'readable' => true,
        ];
    }

    /**
     * Compara a versão recebida pelo participante com a que ele devolveu.
     *
     * @return array<string, mixed> resumo + até 40 trechos alterados + alertas
     */
    public static function compare(array $old, array $new, bool $identicalBytes = false): array
    {
        $a = $old['paragraphs'];
        $b = $new['paragraphs'];

        // Corta início/fim iguais — a maior parte das revisões é localizada.
        $start = 0;
        while ($start < count($a) && $start < count($b) && $a[$start] === $b[$start]) {
            $start++;
        }
        $endA = count($a) - 1;
        $endB = count($b) - 1;
        while ($endA >= $start && $endB >= $start && $a[$endA] === $b[$endB]) {
            $endA--;
            $endB--;
        }

        $midA = array_slice($a, $start, $endA - $start + 1);
        $midB = array_slice($b, $start, $endB - $start + 1);
        [$removed, $added] = self::diffParagraphs($midA, $midB);

        $oldWords = self::bag($a);
        $newWords = self::bag($b);
        $common = 0;
        $wordsAdded = 0;
        foreach ($newWords as $w => $n) {
            $c = min($n, $oldWords[$w] ?? 0);
            $common += $c;
            $wordsAdded += $n - $c;
        }
        $wordsRemoved = array_sum($oldWords) - $common;
        $totalWords = array_sum($oldWords) + array_sum($newWords);
        $similarity = $totalWords > 0 ? round(2 * $common / $totalWords, 3) : 1.0;

        $textChanged = $added !== [] || $removed !== [];
        $trackDelta = ($new['insertions'] + $new['deletions']) - ($old['insertions'] + $old['deletions']);

        $alerts = [];
        if ($identicalBytes) {
            $alerts[] = 'identical';
        } elseif (!$textChanged && $new['comments'] === $old['comments'] && $trackDelta === 0) {
            $alerts[] = 'no_changes';
        }
        if ($textChanged && $trackDelta <= 0 && $new['readable']) {
            $alerts[] = 'untracked';
        }
        if ($new['readable'] && array_sum($oldWords) >= 200 && $similarity < 0.5) {
            $alerts[] = 'different_document';
        }
        if ($new['readable'] && $new['words'] < max(50, (int) ($old['words'] * 0.5)) && $old['words'] >= 200) {
            $alerts[] = 'much_shorter';
        }

        $items = [];
        foreach ($removed as $p) {
            $items[] = ['type' => '-', 'text' => mb_strimwidth($p, 0, 300, '…')];
        }
        foreach ($added as $p) {
            $items[] = ['type' => '+', 'text' => mb_strimwidth($p, 0, 300, '…')];
        }

        return [
            'paragraphs_added' => count($added),
            'paragraphs_removed' => count($removed),
            'words_added' => $wordsAdded,
            'words_removed' => $wordsRemoved,
            'words_before' => $old['words'],
            'words_after' => $new['words'],
            'similarity' => $similarity,
            'tracked_insertions' => $new['insertions'],
            'tracked_deletions' => $new['deletions'],
            'tracked_delta' => $trackDelta,
            'comments' => $new['comments'],
            'comments_delta' => $new['comments'] - $old['comments'],
            'alerts' => $alerts,
            'items' => array_slice($items, 0, 40),
            'items_total' => count($items),
        ];
    }

    public const ALERT_LABELS = [
        'identical' => 'Arquivo idêntico ao recebido (nenhuma alteração)',
        'no_changes' => 'Nenhuma alteração de texto, comentário ou marca de revisão',
        'untracked' => 'Texto alterado sem "Controlar Alterações" ligado',
        'different_document' => 'O conteúdo parece de outro documento (semelhança baixa)',
        'much_shorter' => 'O arquivo ficou muito menor que a versão recebida',
    ];

    public static function summaryLine(array $c): string
    {
        $parts = [
            "+{$c['words_added']}/-{$c['words_removed']} palavras",
            "{$c['paragraphs_added']} parágrafo(s) novo(s)/alterado(s), {$c['paragraphs_removed']} removido(s)/alterado(s)",
            "{$c['tracked_insertions']} inserção(ões) e {$c['tracked_deletions']} exclusão(ões) controladas",
            "{$c['comments']} comentário(s)",
            'semelhança ' . round($c['similarity'] * 100) . '%',
        ];

        $alerts = array_map(fn ($a) => '⚠ ' . (self::ALERT_LABELS[$a] ?? $a), $c['alerts'] ?? []);

        return implode(' · ', array_merge($parts, $alerts));
    }

    /**
     * Diferença por parágrafo (LCS); se o trecho for grande demais, cai para
     * comparação por conjunto.
     *
     * @return array{0: array<int, string>, 1: array<int, string>} [removidos, adicionados]
     */
    protected static function diffParagraphs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if ($n === 0 || $m === 0) {
            return [$a, $b];
        }

        // Tabela LCS cheia custa memória (~40 bytes/célula): acima disso, usa a
        // comparação por conjunto para não estourar o memory_limit no envio.
        if ($n * $m > 250_000) {
            $setB = array_count_values($b);
            $setA = array_count_values($a);
            $removed = array_values(array_filter($a, function ($p) use (&$setB) {
                if (($setB[$p] ?? 0) > 0) {
                    $setB[$p]--;
                    return false;
                }
                return true;
            }));
            $added = array_values(array_filter($b, function ($p) use (&$setA) {
                if (($setA[$p] ?? 0) > 0) {
                    $setA[$p]--;
                    return false;
                }
                return true;
            }));

            return [$removed, $added];
        }

        $len = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $len[$i][$j] = $a[$i] === $b[$j]
                    ? $len[$i + 1][$j + 1] + 1
                    : max($len[$i + 1][$j], $len[$i][$j + 1]);
            }
        }

        $removed = [];
        $added = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $i++;
                $j++;
            } elseif ($len[$i + 1][$j] >= $len[$i][$j + 1]) {
                $removed[] = $a[$i++];
            } else {
                $added[] = $b[$j++];
            }
        }
        while ($i < $n) {
            $removed[] = $a[$i++];
        }
        while ($j < $m) {
            $added[] = $b[$j++];
        }

        return [$removed, $added];
    }

    protected static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    protected static function bag(array $paragraphs): array
    {
        $bag = [];
        foreach ($paragraphs as $p) {
            foreach (self::words($p) as $w) {
                $bag[$w] = ($bag[$w] ?? 0) + 1;
            }
        }

        return $bag;
    }
}
