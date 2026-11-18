<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnerSettings extends Model
{
    protected $table = 'owner_settings';

    protected $fillable = [
        'owner_user_id',
        'telegram_chat_id',
        'telegram_link_token',
        'storage_settings',
    ];

    protected $casts = [
        'storage_settings' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_user_id');
    }
}
