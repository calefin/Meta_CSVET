<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalRecord extends Model
{
    protected $fillable = [
        'record_type',
        'legal_responsible_name', // Nomenclatura obrigatória (não usar tutor)
        'animal_data',
        'clinical_history_or_macroscopy',
        'microscopic_interpretation',
        'final_diagnosis',
        'google_drive_folder_id',
        'status',
    ];

    protected $casts = [
        'animal_data' => 'array',
    ];

    /**
     * Get all of the tags for the clinical record.
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
