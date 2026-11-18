<?php

namespace App\Services;

/**
 * Lê exportações das bases bibliográficas e devolve registros num formato
 * único. Aceita:
 *  - JSON gerado por scripts/revisao_sistematica/buscar.py (com as contagens
 *    por base e as duplicatas que o script já removeu);
 *  - RIS (.ris — Scopus, Web of Science, Embase, CAB, Zotero, Mendeley);
 *  - MEDLINE/PubMed (.nbib / .txt "PMID- ");
 *  - CSV/TSV (Scopus CSV, Web of Science "tab-delimited").
 *
 * Nada é executado: só leitura de texto.
 */
class ReferenceImporter
{
    public const MAX_BYTES = 40 * 1024 * 1024;

    /** Campos bibliográficos complementares guardados em `extra`. */
    public const EXTRA_FIELDS = ['volume', 'issue', 'pages', 'issn', 'pmcid', 'language', 'publication_type', 'record_id'];

    /**
     * @return array{records: array<int, array>, meta: array{sources: array<string,int>, duplicates_removed: int, query: ?string, date: ?string, format: string}}
     */
    public static function parse(string $content, string $filename, ?string $sourceLabel = null): array
    {
        if (strlen($content) > self::MAX_BYTES) {
            throw new \RuntimeException('Arquivo grande demais (máx. 40 MB).');
        }

        $content = self::toUtf8($content);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $trimmed = ltrim($content);

        if ($ext === 'json' || str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return self::parseJson($content, $sourceLabel);
        }

        if (preg_match('/^PMID- /m', $content)) {
            return self::wrap(self::parseMedline($content), $sourceLabel ?: 'PubMed', 'MEDLINE');
        }

        if (preg_match('/^TY  - /m', $content)) {
            return self::wrap(self::parseRis($content), $sourceLabel ?: self::guessRisSource($content), 'RIS');
        }

        if (in_array($ext, ['csv', 'tsv', 'txt'], true)) {
            return self::wrap(self::parseCsv($content), $sourceLabel ?: self::guessCsvSource($content), 'CSV');
        }

        throw new \RuntimeException('Formato não reconhecido. Envie .json (do script), .ris, .nbib (PubMed) ou .csv/.txt (Scopus/WoS).');
    }

    protected static function wrap(array $records, string $source, string $format): array
    {
        foreach ($records as &$r) {
            $r['sources'] = $r['sources'] ?: [$source];
        }

        return [
            'records' => $records,
            'meta' => [
                'sources' => [$source => count($records)],
                'duplicates_removed' => 0,
                'query' => null,
                'date' => null,
                'searches' => [],
                'format' => $format,
            ],
        ];
    }

    protected static function parseJson(string $content, ?string $sourceLabel): array
    {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException('JSON inválido: ' . json_last_error_msg());
        }

        $items = array_is_list($data) ? $data : ($data['records'] ?? null);
        if (!is_array($items)) {
            throw new \RuntimeException('JSON sem a lista "records".');
        }

        $records = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sources = $item['sources'] ?? $item['source'] ?? $sourceLabel ?? 'Importado';
            $records[] = self::normalize([
                'title' => $item['title'] ?? null,
                'abstract' => $item['abstract'] ?? null,
                'authors' => $item['authors'] ?? null,
                'year' => $item['year'] ?? null,
                'journal' => $item['journal'] ?? null,
                'doi' => $item['doi'] ?? null,
                'pmid' => $item['pmid'] ?? null,
                'url' => $item['url'] ?? null,
                'keywords' => $item['keywords'] ?? null,
                'sources' => is_array($sources) ? $sources : [$sources],
                'extra' => array_intersect_key($item, array_flip(self::EXTRA_FIELDS)),
            ]);
        }

        $search = is_array($data['search'] ?? null) ? $data['search'] : [];
        $sourceCounts = [];
        foreach (($search['sources'] ?? []) as $name => $count) {
            $sourceCounts[(string) $name] = (int) $count;
        }
        if (!$sourceCounts) {
            foreach ($records as $r) {
                foreach ($r['sources'] as $s) {
                    $sourceCounts[$s] = ($sourceCounts[$s] ?? 0) + 1;
                }
            }
        }

        return [
            'records' => array_values(array_filter($records, fn ($r) => filled($r['title']))),
            'meta' => [
                'sources' => $sourceCounts,
                'duplicates_removed' => (int) ($search['duplicates_removed'] ?? 0),
                'query' => $search['query'] ?? null,
                'date' => $search['date'] ?? null,
                'searches' => is_array($search['searches'] ?? null) ? array_values(array_filter($search['searches'], 'is_array')) : [],
                'format' => 'JSON',
            ],
        ];
    }

    public static function parseRis(string $content): array
    {
        $records = [];
        $current = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (!preg_match('/^([A-Z][A-Z0-9])  -\s?(.*)$/', $line, $m)) {
                // Continuação de linha (alguns exportadores quebram o resumo).
                if ($current && isset($lastTag) && trim($line) !== '') {
                    $idx = count($current[$lastTag]) - 1;
                    $current[$lastTag][$idx] .= ' ' . trim($line);
                }
                continue;
            }
            [$tag, $value] = [$m[1], trim($m[2])];

            if ($tag === 'ER') {
                if ($current) {
                    $records[] = self::fromRis($current);
                }
                $current = [];
                unset($lastTag);
                continue;
            }
            if ($tag === 'TY') {
                $current = [];
            }
            $current[$tag][] = $value;
            $lastTag = $tag;
        }
        if ($current) {
            $records[] = self::fromRis($current);
        }

        return array_values(array_filter($records, fn ($r) => filled($r['title'])));
    }

    protected static function fromRis(array $t): array
    {
        $first = fn (array $tags) => collect($tags)->map(fn ($k) => $t[$k][0] ?? null)->first(fn ($v) => filled($v));
        $doi = $first(['DO']);
        if (!$doi) {
            foreach (array_merge($t['UR'] ?? [], $t['L3'] ?? [], $t['M3'] ?? []) as $u) {
                if (preg_match('#(10\.\d{4,9}/\S+)#', $u, $m)) {
                    $doi = $m[1];
                    break;
                }
            }
        }
        $pmid = null;
        foreach (array_merge($t['AN'] ?? [], $t['C2'] ?? [], $t['M2'] ?? []) as $v) {
            if (preg_match('/^(?:PMID:?\s*)?(\d{5,9})$/', trim($v), $m)) {
                $pmid = $m[1];
                break;
            }
        }

        return self::normalize([
            'title' => $first(['TI', 'T1', 'CT', 'BT']),
            'abstract' => $first(['AB', 'N2']),
            'authors' => array_merge($t['AU'] ?? [], $t['A1'] ?? []),
            'year' => $first(['PY', 'Y1', 'DA']),
            'journal' => $first(['T2', 'JO', 'JF', 'JA', 'J2']),
            'doi' => $doi,
            'pmid' => $pmid,
            'url' => $first(['UR']),
            'keywords' => $t['KW'] ?? [],
            'extra' => [
                'volume' => $first(['VL']),
                'issue' => $first(['IS']),
                'pages' => trim(($first(['SP']) ?? '') . (($ep = $first(['EP'])) ? '-' . $ep : ''), '-') ?: null,
                'issn' => $first(['SN']),
                'language' => $first(['LA']),
                'publication_type' => $first(['M3', 'TY']),
            ],
        ]);
    }

    public static function parseMedline(string $content): array
    {
        $records = [];
        foreach (preg_split('/\R\s*\R(?=PMID- )/', trim($content)) as $block) {
            $tags = [];
            $last = null;
            foreach (preg_split('/\R/', $block) as $line) {
                if (preg_match('/^([A-Z]{2,4})\s*- (.*)$/', $line, $m)) {
                    $last = $m[1];
                    $tags[$last][] = trim($m[2]);
                } elseif ($last && str_starts_with($line, '      ')) {
                    $tags[$last][count($tags[$last]) - 1] .= ' ' . trim($line);
                }
            }
            $doi = null;
            foreach (array_merge($tags['LID'] ?? [], $tags['AID'] ?? []) as $v) {
                if (str_contains($v, '[doi]')) {
                    $doi = trim(str_replace('[doi]', '', $v));
                    break;
                }
            }
            $records[] = self::normalize([
                'title' => $tags['TI'][0] ?? ($tags['BTI'][0] ?? null),
                'abstract' => $tags['AB'][0] ?? null,
                'authors' => $tags['FAU'] ?? ($tags['AU'] ?? []),
                'year' => $tags['DP'][0] ?? null,
                'journal' => $tags['JT'][0] ?? ($tags['TA'][0] ?? null),
                'doi' => $doi,
                'pmid' => $tags['PMID'][0] ?? null,
                'keywords' => array_merge($tags['OT'] ?? [], $tags['MH'] ?? []),
                'extra' => [
                    'volume' => $tags['VI'][0] ?? null,
                    'issue' => $tags['IP'][0] ?? null,
                    'pages' => $tags['PG'][0] ?? null,
                    'issn' => $tags['IS'][0] ?? null,
                    'pmcid' => $tags['PMC'][0] ?? null,
                    'language' => $tags['LA'][0] ?? null,
                    'publication_type' => isset($tags['PT']) ? implode('; ', $tags['PT']) : null,
                ],
            ]);
        }

        return array_values(array_filter($records, fn ($r) => filled($r['title'])));
    }

    public static function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $headerLine = $lines[0] ?? '';
        $delimiter = substr_count($headerLine, "\t") > substr_count($headerLine, ',') ? "\t"
            : (substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',');

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (!$header) {
            return [];
        }
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h, " \t\"\u{FEFF}")), $header);

        $map = [
            'title' => ['title', 'ti', 'article title', 'document title'],
            'abstract' => ['abstract', 'ab'],
            'authors' => ['authors', 'author full names', 'au', 'af', 'author'],
            'year' => ['year', 'py', 'publication year'],
            'journal' => ['source title', 'so', 'journal', 'publication title', 'source'],
            'doi' => ['doi', 'di'],
            'pmid' => ['pubmed id', 'pm', 'pmid'],
            'url' => ['link', 'url'],
            'keywords' => ['author keywords', 'de', 'keywords', 'index keywords'],
            'sources' => ['sources'],
            'volume' => ['volume', 'vl'],
            'issue' => ['issue', 'is'],
            'pages' => ['pages', 'page start'],
            'issn' => ['issn', 'sn'],
            'pmcid' => ['pmcid'],
            'language' => ['language', 'la', 'language of original document'],
            'publication_type' => ['publication_type', 'document type', 'dt'],
            'record_id' => ['record_id', 'eid', 'ut', 'ut (unique wos id)'],
        ];
        $index = [];
        foreach ($map as $field => $names) {
            foreach ($names as $n) {
                $pos = array_search($n, $header, true);
                if ($pos !== false) {
                    $index[$field] = $pos;
                    break;
                }
            }
        }
        if (!isset($index['title'])) {
            fclose($handle);
            throw new \RuntimeException('CSV sem coluna de título ("Title" ou "TI").');
        }

        $records = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $get = fn ($f) => isset($index[$f]) ? ($row[$index[$f]] ?? null) : null;
            $authors = $get('authors');
            $records[] = self::normalize([
                'title' => $get('title'),
                'abstract' => $get('abstract'),
                'authors' => $authors ? preg_split('/\s*;\s*/', $authors) : [],
                'year' => $get('year'),
                'journal' => $get('journal'),
                'doi' => $get('doi'),
                'pmid' => $get('pmid'),
                'url' => $get('url'),
                'keywords' => $get('keywords'),
                'sources' => filled($get('sources')) ? preg_split('/\s*;\s*/', $get('sources')) : null,
                'extra' => array_combine(self::EXTRA_FIELDS, array_map($get, self::EXTRA_FIELDS)),
            ]);
        }
        fclose($handle);

        return array_values(array_filter($records, fn ($r) => filled($r['title'])));
    }

    protected static function normalize(array $r): array
    {
        $clean = fn ($v) => $v === null ? null : trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        $list = fn ($v) => is_array($v) ? implode('; ', array_filter(array_map($clean, $v))) : $clean($v);

        $year = null;
        if (preg_match('/(1[89]\d{2}|20\d{2})/', (string) ($r['year'] ?? ''), $m)) {
            $year = $m[1];
        }
        $abstract = $clean($r['abstract'] ?? null);
        if ($abstract && in_array(mb_strtolower($abstract), ['[no abstract available]', 'no abstract available'], true)) {
            $abstract = null;
        }

        return [
            'title' => rtrim((string) $clean($r['title'] ?? ''), ' .') ?: null,
            'abstract' => $abstract ?: null,
            'authors' => $list($r['authors'] ?? null) ?: null,
            'year' => $year,
            'journal' => $clean($r['journal'] ?? null) ?: null,
            'doi' => \App\Models\SystematicReviewRecord::normalizeDoi($clean($r['doi'] ?? null)),
            'pmid' => preg_match('/^\d{1,10}$/', (string) $clean($r['pmid'] ?? '')) ? $clean($r['pmid']) : null,
            'url' => filter_var($clean($r['url'] ?? null), FILTER_VALIDATE_URL) && preg_match('#^https?://#i', (string) $r['url']) ? $clean($r['url']) : null,
            'keywords' => $list($r['keywords'] ?? null) ?: null,
            'sources' => array_values(array_filter(array_map('strval', (array) ($r['sources'] ?? [])))),
            'extra' => array_filter(
                array_map(fn ($v) => is_scalar($v) ? \Illuminate\Support\Str::limit((string) $clean($v), 250, '') : null, array_intersect_key((array) ($r['extra'] ?? []), array_flip(self::EXTRA_FIELDS))),
                fn ($v) => filled($v)
            ) ?: null,
        ];
    }

    protected static function guessRisSource(string $content): string
    {
        return match (true) {
            str_contains($content, 'scopus.com') => 'Scopus',
            str_contains($content, 'webofscience') || preg_match('/^DB  - Web of Science/m', $content) === 1 => 'Web of Science',
            str_contains($content, 'embase') => 'Embase',
            str_contains($content, 'cabdirect') || str_contains($content, 'CABI') => 'CAB Abstracts',
            default => 'Arquivo RIS',
        };
    }

    protected static function guessCsvSource(string $content): string
    {
        $head = substr($content, 0, 2000);

        return match (true) {
            str_contains($head, 'Source title') || str_contains($content, 'scopus.com') => 'Scopus',
            preg_match('/^(\x{FEFF})?PT\t/u', $head) === 1 || str_contains($head, "\tUT\t") || str_contains($head, "\tTI\t") => 'Web of Science',
            default => 'Arquivo CSV',
        };
    }

    protected static function toUtf8(string $content): string
    {
        if (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $content);
    }
}
