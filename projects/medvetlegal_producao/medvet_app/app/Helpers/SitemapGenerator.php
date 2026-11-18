<?php

namespace App\Helpers;

use App\Models\Post;

class SitemapGenerator
{
    /**
     * Generate static sitemap.xml files in both public paths.
     */
    public static function generate()
    {
        // Fetch all published posts
        $posts = Post::published()->orderBy('updated_at', 'desc')->get();
        
        $homepageUrl = url('/');
        $blogUrl = url('/blog');
        $termsUrl = url('/termos');

        // Build XML content
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        $xml .= '    <url>' . "\n";
        $xml .= "        <loc>{$homepageUrl}</loc>\n";
        $xml .= "        <changefreq>weekly</changefreq>\n";
        $xml .= "        <priority>1.0</priority>\n";
        $xml .= '    </url>' . "\n";

        // Blog Index
        $xml .= '    <url>' . "\n";
        $xml .= "        <loc>{$blogUrl}</loc>\n";
        $xml .= "        <changefreq>daily</changefreq>\n";
        $xml .= "        <priority>0.9</priority>\n";
        $xml .= '    </url>' . "\n";

        // Terms and LGPD Policy
        $xml .= '    <url>' . "\n";
        $xml .= "        <loc>{$termsUrl}</loc>\n";
        $xml .= "        <changefreq>monthly</changefreq>\n";
        $xml .= "        <priority>0.3</priority>\n";
        $xml .= '    </url>' . "\n";

        // Dynamic Articles
        foreach ($posts as $post) {
            $postUrl = route('blog.show', $post->slug);
            $lastMod = $post->updated_at->toAtomString();
            $xml .= '    <url>' . "\n";
            $xml .= "        <loc>{$postUrl}</loc>\n";
            $xml .= "        <lastmod>{$lastMod}</lastmod>\n";
            $xml .= "        <changefreq>monthly</changefreq>\n";
            $xml .= "        <priority>0.8</priority>\n";
            $xml .= '    </url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";

        // Locations to write sitemap
        $paths = [
            public_path('sitemap.xml'),
            base_path('../public_html/sitemap.xml'),
        ];

        foreach ($paths as $path) {
            $dir = dirname($path);
            if (is_dir($dir)) {
                @file_put_contents($path, $xml);
            }
        }

        return $xml;
    }
}
