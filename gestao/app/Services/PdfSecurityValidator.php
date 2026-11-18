<?php

namespace App\Services;

class PdfSecurityValidator
{
    protected const SUSPICIOUS_MARKERS = ['/JavaScript', '/JS', '/OpenAction', '/Launch', '/EmbeddedFile'];

    /**
     * Confere o conteúdo real do arquivo (magic bytes via finfo), nunca a
     * extensão/MIME informado pelo cliente, e bloqueia marcadores de
     * conteúdo ativo conhecidos por serem vetores de ataque em PDF.
     *
     * @return array{0: bool, 1: ?string} [valido, mensagem_de_erro]
     */
    public static function isValidPdf(string $content): array
    {
        if ($content === '') {
            return [false, 'Arquivo vazio ou ilegível.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($content);

        if ($mime !== 'application/pdf' || !str_starts_with(ltrim($content), '%PDF-')) {
            return [false, 'O arquivo enviado não é um PDF válido.'];
        }

        foreach (self::SUSPICIOUS_MARKERS as $marker) {
            if (str_contains($content, $marker)) {
                return [false, 'Este PDF contém conteúdo ativo não permitido e foi rejeitado por segurança.'];
            }
        }

        return [true, null];
    }

    /**
     * Mesma checagem de assinatura digital (dicionário /Sig, /ByteRange)
     * já usada em AssinaturaAvulsa e DocumentSubmission — centralizada
     * aqui para não duplicar pela terceira vez.
     */
    public static function isDigitallySigned(string $content): bool
    {
        return str_contains($content, '/Type/Sig')
            || str_contains($content, '/Type /Sig')
            || str_contains($content, '/ByteRange');
    }
}
