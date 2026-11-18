<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

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
