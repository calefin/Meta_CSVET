<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    /**
     * Display a listing of published blog posts.
     */
    public function index()
    {
        $posts = Post::published()
            ->with('author')
            ->orderBy('published_at', 'desc')
            ->paginate(6);

        return view('blog.index', compact('posts'));
    }

    /**
     * Display the specified blog post.
     */
    public function show($slug)
    {
        $post = Post::published()
            ->with('author')
            ->where('slug', $slug)
            ->firstOrFail();

        // Get recent posts for the sidebar, excluding the current one
        $recentPosts = Post::published()
            ->where('id', '!=', $post->id)
            ->orderBy('published_at', 'desc')
            ->take(3)
            ->get();

        return view('blog.show', compact('post', 'recentPosts'));
    }
}
