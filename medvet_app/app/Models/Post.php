<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::saved(function ($post) {
            try {
                \App\Helpers\SitemapGenerator::generate();
            } catch (\Exception $e) {
                // Prevent model operations from failing if sitemap generation fails
                logger()->error('Failed to generate sitemap: ' . $e->getMessage());
            }
        });

        static::deleted(function ($post) {
            try {
                \App\Helpers\SitemapGenerator::generate();
            } catch (\Exception $e) {
                logger()->error('Failed to generate sitemap: ' . $e->getMessage());
            }
        });
    }

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content',
        'category',
        'image',
        'author_id',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
