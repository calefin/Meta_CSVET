<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get all of the clinical records that are assigned this tag.
     */
    public function clinicalRecords()
    {
        return $this->morphedByMany(ClinicalRecord::class, 'taggable');
    }
}
