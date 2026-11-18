<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Versão travada da base (extração reconciliada + RoB) com SHA-256. Nunca é alterada. */
class SrDatasetLock extends Model
{
    protected $table = 'sr_dataset_locks';

    protected $fillable = ['systematic_review_id', 'version', 'extraction_csv', 'extraction_sha256', 'rob_csv', 'rob_sha256', 'locked_by', 'justification'];

    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }
}
