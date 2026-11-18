<?php

/**
 * @file plugins/generic/csvetEditorial/Watermark.php
 *
 * @class Watermark
 *
 * @brief Marca d'água personalizada ("DRM social") nos e-books vendidos.
 *
 *  Só vale para arquivos pagos (preço > 0) entregues a quem comprou: obras abertas (CC BY)
 *  podem ser compartilhadas pela própria licença. Cada exemplar sai com o nome e o e-mail do
 *  comprador, o número do pedido, a data e um código de verificação:
 *  - PDF: rodapé discreto em todas as páginas (mPDF + FPDI, que já vêm com o OMP); os links
 *    externos (DOI, URLs) são preservados. A FPDI gratuita só lê PDF sem compressão moderna:
 *    o PDF-matriz de obra comercial deve ser exportado em PDF/A-1b (ver PLANO_EDITORIAL.md);
 *  - EPUB: os mesmos dados na página de créditos.
 *
 *  O código é um HMAC de comprador + arquivo + pedido com a chave do sistema; quem tiver um
 *  arquivo vazado descobre a origem com `php scripts/verify-copy.php CÓDIGO`.
 *  Se o carimbo falhar, o arquivo NÃO é entregue sem marca: o leitor vê um aviso e o erro vai
 *  para o log do PHP. O exemplar carimbado fica em cache (files_dir/csvet-watermark) e é
 *  refeito se o arquivo original mudar.
 */

namespace APP\plugins\generic\csvetEditorial;

use APP\core\Application;
use APP\payment\omp\OMPPaymentManager;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PdfReader;

class Watermark
{
    /** Hook CatalogBookHandler::download: [&$handler, &$submission, &$publicationFormat, &$submissionFile, &$inline] */
    public function download(string $hookName, array $args): bool
    {
        $submissionFile = $args[3];
        $inline = (bool) $args[4];

        $price = $submissionFile->getDirectSalesPrice();
        if ($price === null || (float) $price <= 0 || $submissionFile->getData('assocType') != Application::ASSOC_TYPE_PUBLICATION_FORMAT) {
            return false;
        }
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $payment = $user ? self::payment($user->getId(), $submissionFile->getId()) : null;
        if (!$payment) {
            return false; // o OMP já só chega aqui com compra paga; na dúvida, segue o fluxo padrão
        }

        $file = app()->get('file')->get($submissionFile->getData('fileId'));
        $source = rtrim(Config::getVar('files', 'files_dir'), '/') . '/' . $file->path;
        $extension = strtolower(pathinfo($file->path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'epub'], true)) {
            return false;
        }

        $code = self::code($user->getId(), $submissionFile->getId(), $payment->completed_payment_id);
        $date = date('d/m/Y', strtotime($payment->timestamp));
        $license = [
            'name' => $user->getFullName(),
            'email' => $user->getEmail(),
            'order' => $payment->completed_payment_id,
            'date' => $date,
            'code' => $code,
        ];

        try {
            $target = self::cacheDir() . "/{$code}.{$extension}";
            if (!is_file($target) || filemtime($target) < filemtime($source)) {
                $extension === 'pdf' ? self::stampPdf($source, $target, $license) : self::stampEpub($source, $target, $license);
            }
        } catch (\Throwable $e) {
            error_log("csvetEditorial watermark: arquivo {$submissionFile->getId()} ({$extension}): " . $e->getMessage());
            self::fail();
            return true;
        }

        $name = app()->get('file')->formatFilename($file->path, $submissionFile->getLocalizedData('name'));
        header('Content-Type: ' . ($extension === 'pdf' ? 'application/pdf' : 'application/epub+zip'));
        header('Content-Length: ' . filesize($target));
        header('Content-Disposition: ' . ($inline && $extension === 'pdf' ? 'inline' : 'attachment') . '; filename="' . addcslashes($name, '"\\') . '"');
        header('Cache-Control: private, no-store');
        readfile($target);
        return true;
    }

    /** Pagamento concluído da compra deste arquivo por este usuário (o mais recente) */
    public static function payment(int $userId, int $submissionFileId): ?object
    {
        return DB::table('completed_payments')
            ->where('payment_type', OMPPaymentManager::PAYMENT_TYPE_PURCHASE_FILE)
            ->where('user_id', $userId)
            ->where('assoc_id', $submissionFileId)
            ->orderByDesc('completed_payment_id')
            ->first();
    }

    /** Código de verificação do exemplar: XXXX-XXXX-XX (HMAC com a chave do sistema) */
    public static function code(int $userId, int $submissionFileId, int $paymentId): string
    {
        $key = Config::getVar('general', 'app_key') ?: Config::getVar('security', 'salt');
        $hash = strtoupper(substr(hash_hmac('sha256', "{$userId}|{$submissionFileId}|{$paymentId}", (string) $key), 0, 10));
        return substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 2);
    }

    /** Texto da licença do exemplar (duas linhas) */
    public static function lines(array $license): array
    {
        return [
            "Exemplar licenciado para {$license['name']} <{$license['email']}> · pedido nº {$license['order']} · {$license['date']}",
            "Código {$license['code']} · Uso pessoal: reprodução, compartilhamento e uso para treinar IA proibidos (Lei nº 9.610/1998) · books.csvet.com.br",
        ];
    }

    public static function stampPdf(string $source, string $target, array $license): void
    {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'tempDir' => self::cacheDir() . '/tmp',
            'default_font' => 'dejavusanscondensed',
            'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0,
            'margin_header' => 0, 'margin_footer' => 0,
        ]);
        $mpdf->SetCreator('Editora CSVET');
        $mpdf->SetSubject("Exemplar licenciado para {$license['name']} - código {$license['code']}");
        $pages = $mpdf->setSourceFile($source);
        // A importação de páginas perde o sumário lateral e os links internos: lidos do original
        // e recriados abaixo (se falhar, o exemplar sai marcado, só sem a navegação)
        try {
            $nav = self::navigation($source);
        } catch (\Throwable $e) {
            error_log('csvetEditorial watermark: navegação não recriada: ' . $e->getMessage());
            $nav = ['bookmarks' => [], 'links' => []];
        }
        [$first, $second] = self::lines($license);
        for ($i = 1; $i <= $pages; $i++) {
            $template = $mpdf->importPage($i);
            $size = $mpdf->getTemplateSize($template);
            $mpdf->AddPageByArray([
                'orientation' => $size['width'] > $size['height'] ? 'L' : 'P',
                'sheet-size' => [$size['width'], $size['height']],
                'margin-left' => 0, 'margin-right' => 0, 'margin-top' => 0, 'margin-bottom' => 0,
            ]);
            $mpdf->useTemplate($template);
            foreach ($nav['bookmarks'][$i] ?? [] as [$title, $level, $y]) {
                $mpdf->Bookmark($title, $level, $y);
            }
            foreach ($nav['links'][$i] ?? [] as [$x, $y, $w, $h, $targetPage, $targetY]) {
                $link = $mpdf->AddLink();
                $mpdf->SetLink($link, $targetY, $targetPage);
                $mpdf->Link($x, $y, $w, $h, $link);
            }
            // Rodapé em cinza, 6 pt, reduzido até caber na largura da página (com 8 mm de margem)
            $mpdf->SetTextColor(120, 120, 135);
            $fontSize = 6;
            $mpdf->SetFont('dejavusanscondensed', '', $fontSize);
            while ($fontSize > 4 && max($mpdf->GetStringWidth($first), $mpdf->GetStringWidth($second)) > $size['width'] - 16) {
                $mpdf->SetFont('dejavusanscondensed', '', $fontSize -= 0.25);
            }
            $mpdf->WriteText(8, $size['height'] - 6, $first);
            $mpdf->WriteText(8, $size['height'] - 3.2, $second);
        }
        $mpdf->Output($target, \Mpdf\Output\Destination::FILE);
    }

    /**
     * Sumário (marcadores) e links internos do PDF original, em mm a partir do canto superior
     * esquerdo de cada página: ['bookmarks' => [página => [[título, nível, y]]],
     * 'links' => [página => [[x, y, largura, altura, página de destino, y de destino]]]].
     */
    public static function navigation(string $source): array
    {
        $parser = new PdfParser(StreamReader::createByFile($source));
        $reader = new PdfReader($parser);
        $resolve = fn ($value) => $value === null ? null : PdfType::resolve($value, $parser);
        // PdfDictionary::get devolve PdfNull (e não null) para chave ausente
        $get = fn ($dictionary, string $key) => ($v = PdfDictionary::get($dictionary, $key)) instanceof PdfNull ? null : $v;
        $mm = 25.4 / 72;

        // Páginas: número do objeto → número da página, e o topo/esquerda de cada uma
        $pageOf = $top = $left = [];
        for ($i = 1, $n = $reader->getPageCount(); $i <= $n; $i++) {
            $page = $reader->getPage($i);
            $pageOf[$page->getPageObject()->objectNumber] = $i;
            $box = $page->getBoundary();
            $top[$i] = $box->getUry();
            $left[$i] = $box->getLlx();
        }

        // Destinos com nome (hyperref/LaTeX e Word usam a árvore /Names /Dests ou o /Dests antigo)
        $catalog = $parser->getCatalog();
        $named = [];
        $walk = function ($node) use (&$walk, &$named, $resolve, $get) {
            $node = $resolve($node);
            if (!$node instanceof PdfDictionary) {
                return;
            }
            $pairs = $resolve($get($node, 'Names'));
            if ($pairs instanceof PdfArray) {
                for ($k = 0; $k + 1 < count($pairs->value); $k += 2) {
                    $named[self::pdfText($resolve($pairs->value[$k]), false)] = $pairs->value[$k + 1];
                }
            }
            $kids = $resolve($get($node, 'Kids'));
            foreach ($kids instanceof PdfArray ? $kids->value : [] as $kid) {
                $walk($kid);
            }
        };
        $names = $resolve($get($catalog, 'Names'));
        if ($names instanceof PdfDictionary) {
            $walk($get($names, 'Dests'));
        }
        $oldDests = $resolve($get($catalog, 'Dests'));
        if ($oldDests instanceof PdfDictionary) {
            foreach ($oldDests->value as $key => $value) {
                $named[$key] = $value;
            }
        }

        // Destino → [página, y em mm a partir do topo]
        $target = function ($dest) use ($resolve, $get, $named, $pageOf, $top, $mm) {
            $dest = $resolve($dest);
            if ($dest instanceof PdfName || $dest instanceof PdfString || $dest instanceof PdfHexString) {
                $key = $dest instanceof PdfName ? $dest->value : self::pdfText($dest, false);
                $dest = isset($named[$key]) ? $resolve($named[$key]) : null;
            }
            if ($dest instanceof PdfDictionary) {
                $dest = $resolve($get($dest, 'D'));
            }
            if (!$dest instanceof PdfArray || !($dest->value[0] ?? null) instanceof PdfIndirectObjectReference) {
                return null;
            }
            $page = $pageOf[$dest->value[0]->value] ?? null;
            if (!$page) {
                return null;
            }
            $type = $resolve($dest->value[1] ?? null);
            $y = match ($type instanceof PdfName ? $type->value : '') {
                'XYZ' => $resolve($dest->value[3] ?? null),
                'FitH', 'FitBH' => $resolve($dest->value[2] ?? null),
                default => null,
            };
            $y = $y instanceof PdfNumeric ? max(0, ($top[$page] - $y->value) * $mm) : 0;
            return [$page, $y];
        };

        // Marcadores (/Outlines), na ordem e com a hierarquia do original
        $bookmarks = [];
        $outline = function ($item, int $level) use (&$outline, &$bookmarks, $resolve, $get, $target) {
            for ($guard = 0; $item && $guard < 5000; $guard++) {
                $item = $resolve($item);
                if (!$item instanceof PdfDictionary) {
                    return;
                }
                $action = $resolve($get($item, 'A'));
                $dest = $get($item, 'Dest') ?? ($action instanceof PdfDictionary ? $get($action, 'D') : null);
                $to = $dest ? $target($dest) : null;
                if ($to) {
                    $bookmarks[$to[0]][] = [self::pdfText($resolve($get($item, 'Title'))), $level, $to[1]];
                }
                if ($child = $get($item, 'First')) {
                    $outline($child, $level + 1);
                }
                $item = $get($item, 'Next');
            }
        };
        $outlines = $resolve($get($catalog, 'Outlines'));
        if ($outlines instanceof PdfDictionary && ($firstItem = $get($outlines, 'First'))) {
            $outline($firstItem, 0);
        }

        // Links internos (/GoTo ou /Dest) de cada página
        $links = [];
        foreach ($pageOf as $i) {
            $annots = $resolve($reader->getPage($i)->getAttribute('Annots', false));
            foreach ($annots instanceof PdfArray ? $annots->value : [] as $annot) {
                $annot = $resolve($annot);
                if (!$annot instanceof PdfDictionary || ($resolve($get($annot, 'Subtype'))->value ?? '') !== 'Link') {
                    continue;
                }
                $action = $resolve($get($annot, 'A'));
                $dest = $get($annot, 'Dest');
                if (!$dest && $action instanceof PdfDictionary && ($resolve($get($action, 'S'))->value ?? '') === 'GoTo') {
                    $dest = $get($action, 'D');
                }
                $rect = $resolve($get($annot, 'Rect'));
                $to = $dest ? $target($dest) : null;
                if (!$to || !$rect instanceof PdfArray || count($rect->value) < 4) {
                    continue;
                }
                [$x1, $y1, $x2, $y2] = array_map(fn ($v) => (float) $resolve($v)->value, array_slice($rect->value, 0, 4));
                $links[$i][] = [
                    (min($x1, $x2) - $left[$i]) * $mm, ($top[$i] - max($y1, $y2)) * $mm,
                    abs($x2 - $x1) * $mm, abs($y2 - $y1) * $mm, $to[0], $to[1],
                ];
            }
        }
        $parser->cleanUp();

        return ['bookmarks' => $bookmarks, 'links' => $links];
    }

    /** Texto de uma string PDF (literal ou hexadecimal; UTF-16 com BOM ou PDFDocEncoding) */
    private static function pdfText($value, bool $decode = true): string
    {
        if ($value instanceof PdfHexString) {
            $raw = hex2bin(preg_replace('/[^0-9A-Fa-f]/', '', $value->value) . (strlen(preg_replace('/[^0-9A-Fa-f]/', '', $value->value)) % 2 ? '0' : ''));
        } elseif ($value instanceof PdfString) {
            $raw = PdfString::unescape($value->value);
        } else {
            return '';
        }
        if (!$decode) {
            return $raw;
        }
        if (str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }
        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    }

    public static function stampEpub(string $source, string $target, array $license): void
    {
        if (!copy($source, $target)) {
            throw new \RuntimeException('não foi possível copiar o EPUB');
        }
        $zip = new \ZipArchive();
        if ($zip->open($target) !== true) {
            throw new \RuntimeException('EPUB inválido');
        }
        $notice = '<div class="aviso licenca" style="margin-top:1.5em;font-size:.8em">'
            . implode('<br/>', array_map(fn ($line) => htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8'), self::lines($license)))
            . '</div>';
        // Página de créditos gerada por branding/build_epub.py; sem ela, o primeiro capítulo
        $credits = $firstChapter = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match('/\.x?html$/i', $name) || preg_match('/(nav|cover|toc)\.x?html$/i', $name)) {
                continue;
            }
            $html = $zip->getFromIndex($i);
            if (!$credits && str_contains($html, 'class="creditos"')) {
                $credits = $name;
            }
            $firstChapter ??= $name;
        }
        $page = $credits ?? $firstChapter;
        if (!$page) {
            $zip->close();
            throw new \RuntimeException('EPUB sem páginas de conteúdo');
        }
        $html = $zip->getFromName($page);
        $html = preg_replace('#</body>#i', $notice . '</body>', $html, 1, $count);
        if (!$count || !$zip->addFromString($page, $html)) {
            $zip->close();
            throw new \RuntimeException("não foi possível marcar {$page}");
        }
        $zip->close();
    }

    private static function cacheDir(): string
    {
        $dir = rtrim(Config::getVar('files', 'files_dir'), '/') . '/csvet-watermark';
        is_dir("{$dir}/tmp") || mkdir("{$dir}/tmp", 0750, true);
        return $dir;
    }

    /** O arquivo nunca sai sem marca: aviso ao leitor em vez do download */
    private static function fail(): void
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Preparando seu exemplar | Editora CSVET</title>'
            . '<body style="font-family:Roboto,Arial,sans-serif;max-width:560px;margin:3rem auto;padding:0 1rem;color:#1d2233">'
            . '<h1 style="color:#000080;font-size:1.4rem">Não foi possível preparar seu exemplar agora</h1>'
            . '<p>Sua compra está registrada. Tente de novo em alguns minutos; se o problema continuar, escreva para '
            . '<strong>contato@csvet.com.br</strong> informando o título do livro, e enviaremos o arquivo.</p>'
            . '<p lang="en"><small>Your purchase is recorded, but we could not prepare your copy right now. Please try again later or contact contato@csvet.com.br.</small></p>'
            . '</body></html>';
    }
}
