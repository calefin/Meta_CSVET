<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'status',
        'filename',
        'size_bytes',
        'message',
    ];
}
