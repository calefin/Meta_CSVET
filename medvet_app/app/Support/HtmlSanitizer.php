<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'a'];

    public static function clean(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $wrapper = $dom->getElementsByTagName('div')->item(0);

        if (!$wrapper) {
            return '';
        }

        self::sanitizeNode($wrapper);

        $result = '';
        foreach (iterator_to_array($wrapper->childNodes) as $child) {
            $result .= $dom->saveHTML($child);
        }

        return trim($result);
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (!$child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }

            $href = $tag === 'a' ? $child->getAttribute('href') : null;

            foreach (iterator_to_array($child->attributes) as $attr) {
                $child->removeAttribute($attr->name);
            }

            if ($tag === 'a') {
                if ($href !== null && preg_match('/^(https?:|mailto:)/i', trim($href))) {
                    $child->setAttribute('href', $href);
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'nofollow noopener noreferrer');
                } else {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
            }

            self::sanitizeNode($child);
        }
    }
}
