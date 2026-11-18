<?php

namespace App\Livewire\Shared;

use Livewire\Component;

class LanguageSwitcher extends Component
{
    /**
     * Pages with real per-locale URLs (needed for hreflang/SEO to mean
     * anything). Everything else falls back to session-based switching.
     */
    private const LOCALIZED_ROUTES = [
        'home' => ['pt-BR' => 'home', 'en' => 'home.en', 'es' => 'home.es'],
        'home.en' => ['pt-BR' => 'home', 'en' => 'home.en', 'es' => 'home.es'],
        'home.es' => ['pt-BR' => 'home', 'en' => 'home.en', 'es' => 'home.es'],
        'privacy' => ['pt-BR' => 'privacy', 'en' => 'privacy.en', 'es' => 'privacy.es'],
        'privacy.en' => ['pt-BR' => 'privacy', 'en' => 'privacy.en', 'es' => 'privacy.es'],
        'privacy.es' => ['pt-BR' => 'privacy', 'en' => 'privacy.en', 'es' => 'privacy.es'],
    ];

    public function setLocale(string $locale): void
    {
        if (! in_array($locale, ['pt-BR', 'en', 'es'], true)) {
            return;
        }

        session(['locale' => $locale]);

        if ($user = auth()->user()) {
            $user->update(['locale' => $locale]);
        }

        $this->redirect(request()->header('Referer') ?? route('home'), navigate: false);
    }

    public function render()
    {
        $routeName = request()->route()?->getName();
        $localizedUrls = isset(self::LOCALIZED_ROUTES[$routeName])
            ? array_map(fn ($name) => route($name), self::LOCALIZED_ROUTES[$routeName])
            : null;

        return view('livewire.shared.language-switcher', [
            'current' => app()->getLocale(),
            'localizedUrls' => $localizedUrls,
            'locales' => [
                'pt-BR' => 'PT',
                'en' => 'EN',
                'es' => 'ES',
            ],
        ]);
    }
}
