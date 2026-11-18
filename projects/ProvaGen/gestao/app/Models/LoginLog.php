<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'status',
        'login_at',
    ];

    protected $casts = [
        'login_at' => 'datetime',
    ];

    /**
     * Só orientandos logam aqui (login de professor é via SSO — ver
     * AppServiceProvider, que filtra o guard antes de criar o registro).
     */
    public function user()
    {
        return $this->belongsTo(AdviseeAccount::class, 'user_id');
    }
}
