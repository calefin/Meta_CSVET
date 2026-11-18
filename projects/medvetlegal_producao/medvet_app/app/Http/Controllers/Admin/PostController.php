<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Display a listing of posts.
     */
    public function index()
    {
        $posts = Post::with('author')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.posts.index', compact('posts'));
    }

    /**
     * Show the form for creating a new post.
     */
    public function create()
    {
        return view('admin.posts.create');
    }

    /**
     * Store a newly created post in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'required|string|max:1000',
            'category' => 'required|string|max:100',
            'content' => 'required|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'published_at' => 'nullable|date',
        ]);

        // Generate unique slug
        $slug = Str::slug($validated['title']);
        $originalSlug = $slug;
        $count = 1;
        while (Post::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image_file')) {
            $imagePath = $request->file('image_file')->store('posts', 'public');
        }

        Post::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'summary' => $validated['summary'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'image' => $imagePath ? 'storage/' . $imagePath : null,
            'author_id' => auth()->id(),
            'published_at' => $validated['published_at'] ? \Carbon\Carbon::parse($validated['published_at']) : null,
        ]);

        return redirect()->route('admin.posts.index')->with('success', 'Artigo criado com sucesso!');
    }

    /**
     * Show the form for editing the specified post.
     */
    public function edit(Post $post)
    {
        return view('admin.posts.edit', compact('post'));
    }

    /**
     * Update the specified post in storage.
     */
    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'required|string|max:1000',
            'category' => 'required|string|max:100',
            'content' => 'required|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'published_at' => 'nullable|date',
        ]);

        // Generate unique slug if title changed
        $slug = $post->slug;
        if ($post->title !== $validated['title']) {
            $slug = Str::slug($validated['title']);
            $originalSlug = $slug;
            $count = 1;
            while (Post::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }
        }

        // Handle image upload
        $imagePath = $post->image;
        if ($request->hasFile('image_file')) {
            // Delete old image file if it exists in storage
            if ($post->image && Str::startsWith($post->image, 'storage/posts/')) {
                $oldFile = Str::after($post->image, 'storage/');
                Storage::disk('public')->delete($oldFile);
            }
            // Store new image
            $uploadedPath = $request->file('image_file')->store('posts', 'public');
            $imagePath = 'storage/' . $uploadedPath;
        }

        $post->update([
            'title' => $validated['title'],
            'slug' => $slug,
            'summary' => $validated['summary'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'image' => $imagePath,
            'published_at' => $validated['published_at'] ? \Carbon\Carbon::parse($validated['published_at']) : null,
        ]);

        return redirect()->route('admin.posts.index')->with('success', 'Artigo atualizado com sucesso!');
    }

    /**
     * Remove the specified post from storage.
     */
    public function destroy(Post $post)
    {
        // Delete image file from storage if present
        if ($post->image && Str::startsWith($post->image, 'storage/posts/')) {
            $oldFile = Str::after($post->image, 'storage/');
            Storage::disk('public')->delete($oldFile);
        }

        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', 'Artigo excluído com sucesso!');
    }
}
