<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate dynamic XML sitemap and write static files.
     */
    public function index()
    {
        try {
            $content = \App\Helpers\SitemapGenerator::generate();
        } catch (\Exception $e) {
            $posts = Post::published()->orderBy('updated_at', 'desc')->get();
            $content = view('sitemap', compact('posts'))->render();
            logger()->error('Failed to write static sitemap, rendering dynamically: ' . $e->getMessage());
        }

        return response($content, 200)
            ->header('Content-Type', 'text/xml');
    }
}
