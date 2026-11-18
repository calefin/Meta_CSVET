<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Execução do script R sobre uma versão travada, com reexecução independente. */
class SrAnalysisRun extends Model
{
    protected $table = 'sr_analysis_runs';

    protected $fillable = [
        'systematic_review_id', 'sr_dataset_lock_id', 'script_name', 'script_path', 'script_sha256', 'outputs', 'session_info',
        'run_by', 'notes', 'rerun_by', 'rerun_at', 'rerun_matches', 'rerun_notes', 'post_hoc',
    ];

    protected $casts = [
        'outputs' => 'array',
        'rerun_at' => 'datetime',
        'rerun_matches' => 'boolean',
        'post_hoc' => 'boolean',
    ];

    public function lock()
    {
        return $this->belongsTo(SrDatasetLock::class, 'sr_dataset_lock_id');
    }

    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }
}
