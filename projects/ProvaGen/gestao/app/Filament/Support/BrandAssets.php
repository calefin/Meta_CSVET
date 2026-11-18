<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

/**
 * Identidade visual do Prof-Aid, compartilhada entre AdminPanelProvider e
 * StudentPanelProvider — mesma marca do prof-aid.com (ícone pen-line +
 * indigo/azul, ver client/src/index.css --primary e favicon.svg do Node).
 * Logo inline (não um <img src>) pra herdar a cor do texto do painel
 * (currentColor) automaticamente no claro/escuro, sem precisar de um
 * arquivo de logo separado pra cada tema.
 */
class BrandAssets
{
    public static function logo(): HtmlString
    {
        return new HtmlString(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 172 40" height="40" style="display:block">
                <defs>
                    <linearGradient id="pa-brand-grad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#312e81"/>
                        <stop offset="100%" stop-color="#1d4ed8"/>
                    </linearGradient>
                </defs>
                <rect width="40" height="40" rx="10" fill="url(#pa-brand-grad)"/>
                <g transform="translate(10,10) scale(1.25)" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9"/>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                </g>
                <text x="50" y="27" font-family="Outfit, sans-serif" font-weight="800" font-size="21" fill="currentColor">Prof-Aid</text>
            </svg>
            SVG);
    }

    public static function faviconUrl(): string
    {
        return asset('images/prof-aid-mark.svg');
    }
}
