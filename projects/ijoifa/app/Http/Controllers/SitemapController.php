<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $localeAlternates = [
            'pt-BR' => route('home'),
            'en' => route('home.en'),
            'es' => route('home.es'),
            'x-default' => route('home'),
        ];

        $consultationAlternates = [
            'pt-BR' => route('public-consultation-request'),
            'en' => route('public-consultation-request.en'),
            'es' => route('public-consultation-request.es'),
            'x-default' => route('public-consultation-request'),
        ];

        $privacyAlternates = [
            'pt-BR' => route('privacy'),
            'en' => route('privacy.en'),
            'es' => route('privacy.es'),
            'x-default' => route('privacy'),
        ];

        $pages = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly', 'alternates' => $localeAlternates],
            ['loc' => route('home.en'), 'priority' => '0.8', 'changefreq' => 'weekly', 'alternates' => $localeAlternates],
            ['loc' => route('home.es'), 'priority' => '0.8', 'changefreq' => 'weekly', 'alternates' => $localeAlternates],
            ['loc' => route('public-consultation-request'), 'priority' => '0.9', 'changefreq' => 'weekly', 'alternates' => $consultationAlternates],
            ['loc' => route('public-consultation-request.en'), 'priority' => '0.8', 'changefreq' => 'weekly', 'alternates' => $consultationAlternates],
            ['loc' => route('public-consultation-request.es'), 'priority' => '0.8', 'changefreq' => 'weekly', 'alternates' => $consultationAlternates],
            ['loc' => route('privacy'), 'priority' => '0.3', 'changefreq' => 'yearly', 'alternates' => $privacyAlternates],
            ['loc' => route('privacy.en'), 'priority' => '0.2', 'changefreq' => 'yearly', 'alternates' => $privacyAlternates],
            ['loc' => route('privacy.es'), 'priority' => '0.2', 'changefreq' => 'yearly', 'alternates' => $privacyAlternates],
            ['loc' => route('blog.index'), 'priority' => '0.7', 'changefreq' => 'weekly', 'alternates' => null],
        ];

        $articles = Article::published()->get();

        return response()
            ->view('sitemap', ['pages' => $pages, 'articles' => $articles])
            ->header('Content-Type', 'application/xml');
    }
}
