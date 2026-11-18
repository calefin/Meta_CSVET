<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'order',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(LibraryDocument::class, 'category_id')->orderBy('order');
    }

    public function grantedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'library_category_access', 'category_id', 'user_id')
            ->withPivot('granted_by', 'granted_at');
    }
}
