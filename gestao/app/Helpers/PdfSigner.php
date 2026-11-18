<?php

namespace App\Helpers;

use setasign\Fpdi\Fpdi;

class PdfSigner
{
    /**
     * Adiciona um selo visual de assinatura digital ICP-Brasil / Forense no PDF.
     */
    public static function stampSignature(string $pdfPath, array $metadata, string $pageChoice, string $positionChoice, string $outputDisk = 'public'): bool
    {
        $disk = \Illuminate\Support\Facades\Storage::disk($outputDisk);
        if (!$disk->exists($pdfPath)) {
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($pdfPath)) {
                $pdfContent = \Illuminate\Support\Facades\Storage::disk('local')->get($pdfPath);
            } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($pdfPath)) {
                $pdfContent = \Illuminate\Support\Facades\Storage::disk('public')->get($pdfPath);
            } else {
                return false;
            }
        } else {
            $pdfContent = $disk->get($pdfPath);
        }

        $tempInputPath = tempnam(sys_get_temp_dir(), 'pdf_in');
        file_put_contents($tempInputPath, $pdfContent);

        $tempOutputPath = tempnam(sys_get_temp_dir(), 'pdf_out');

        $pdf = new Fpdi();
        
        try {
            $pageCount = $pdf->setSourceFile($tempInputPath);
        } catch (\Exception $e) {
            @unlink($tempInputPath);
            @unlink($tempOutputPath);
            throw new \Exception("Erro ao ler arquivo PDF para inserção de assinatura: " . $e->getMessage());
        }

        $signerName = $metadata['signer_name'] ?? 'Dr. Atilio Sersun Calefi';
        $timestamp = $metadata['forensic_timestamp'] ?? now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s');
        $hash = $metadata['forensic_hash'] ?? '';
        $shortHash = mb_substr($hash, 0, 16) . '...';
        
        $signatureText = "Assinado digitalmente por:\n$signerName\nData/Hora: $timestamp\nSHA-256: $shortHash\nPortal profatilio.com.br";

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $shouldStamp = false;
            if ($pageChoice === 'all') {
                $shouldStamp = true;
            } elseif ($pageChoice === 'first' && $pageNo === 1) {
                $shouldStamp = true;
            } elseif ($pageChoice === 'last' && $pageNo === $pageCount) {
                $shouldStamp = true;
            }

            if ($shouldStamp) {
                $pdf->SetFont('Arial', '', 7);
                $pdf->SetTextColor(80, 80, 80);

                // Selo visual: 68mm x 18mm
                $w = 68;
                $h = 18;
                $margin = 10;
                $pageWidth = $size['width'];
                $pageHeight = $size['height'];

                switch ($positionChoice) {
                    case 'top-left':
                        $x = $margin;
                        $y = $margin;
                        break;
                    case 'top-right':
                        $x = $pageWidth - $w - $margin;
                        $y = $margin;
                        break;
                    case 'bottom-left':
                        $x = $margin;
                        $y = $pageHeight - $h - $margin;
                        break;
                    case 'footer-center':
                        $x = ($pageWidth - $w) / 2;
                        $y = $pageHeight - $h - $margin;
                        break;
                    case 'bottom-right':
                    default:
                        $x = $pageWidth - $w - $margin;
                        $y = $pageHeight - $h - $margin;
                        break;
                }

                // Fundo branco com borda suave
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetDrawColor(13, 148, 136); // Teal
                $pdf->Rect($x, $y, $w, $h, 'DF');

                // Linha lateral em cor teal sólida
                $pdf->SetFillColor(13, 148, 136);
                $pdf->Rect($x, $y, 2, $h, 'F');

                // QR Code
                if (!empty($hash)) {
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=" . urlencode(url("/documento/validar/{$hash}"));
                    try {
                        // Baixa o QR Code em timeout rápido
                        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                        $qrContent = @file_get_contents($qrUrl, false, $ctx);
                        if ($qrContent) {
                            $tempQr = tempnam(sys_get_temp_dir(), 'qr');
                            file_put_contents($tempQr, $qrContent);
                            $pdf->Image($tempQr, $x + $w - 15, $y + 2.5, 13, 13, 'PNG');
                            @unlink($tempQr);
                        }
                    } catch (\Exception $e) {
                        // Ignora se indisponível
                    }
                }

                $pdf->SetXY($x + 3, $y + 2);
                $pdf->MultiCell($w - 18, 3.2, utf8_decode($signatureText), 0, 'L');
            }
        }

        $pdf->Output($tempOutputPath, 'F');
        $signedContent = file_get_contents($tempOutputPath);

        @unlink($tempInputPath);
        @unlink($tempOutputPath);

        $disk->put($pdfPath, $signedContent);
        return true;
    }
}
