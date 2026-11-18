<?php

namespace App\Support;

class SimpleMarkdown
{
    /**
     * Converte o subconjunto de markdown usado nos textos de notificação (apenas
     * **negrito**) para HTML seguro. O texto é escapado primeiro, então os `**`
     * literais (não interpretados como HTML) são trocados por <strong>.
     */
    public static function toHtml(string $text): string
    {
        return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e($text));
    }
}
