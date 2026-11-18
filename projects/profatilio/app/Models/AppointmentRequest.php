<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentRequest extends Model
{
    protected $fillable = [
        'requester_name',
        'requester_email',
        'requester_phone',
        'animal_type',
        'species',
        'complaint',
        'admin_notes',
        'status',
    ];
}
