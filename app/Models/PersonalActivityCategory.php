<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalActivityCategory extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function activities()
    {
        return $this->hasMany(PersonalActivity::class, 'category_id');
    }
}
