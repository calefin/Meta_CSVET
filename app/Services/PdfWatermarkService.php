<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Stamps a tiled, semi-transparent watermark onto every page of an
 * already-finished PDF file (a real book/apostila), unlike dompdf which can
 * only render fresh HTML — FPDI can import and overlay existing PDF pages.
 */
class PdfWatermarkService
{
    public static function stamp(string $sourceAbsolutePath, array $stampLines): string
    {
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile($sourceAbsolutePath);
        $stamp = implode('   •   ', $stampLines);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            self::drawTiledWatermark($pdf, $size['width'], $size['height'], $stamp);
        }

        return $pdf->Output('', 'S');
    }

    private static function drawTiledWatermark(Fpdi $pdf, float $width, float $height, string $stamp): void
    {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(45, 106, 79);
        $pdf->setAlpha(0.14);

        $rows = 6;
        $cols = 2;

        for ($row = 0; $row <= $rows; $row++) {
            for ($col = 0; $col <= $cols; $col++) {
                $x = ($col * ($width / $cols)) - ($width * 0.15);
                $y = ($row * ($height / $rows)) + 5;

                $pdf->StartTransform();
                $pdf->Rotate(-35, $x, $y);
                $pdf->Text($x, $y, $stamp);
                $pdf->StopTransform();
            }
        }

        $pdf->setAlpha(1);
    }
}
