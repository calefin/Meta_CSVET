<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mensagem extends Model
    {
       use HasFactory;

     protected $table = 'mensagens';

     protected $fillable = [
        'ticket_id',
        'ticket_id',
        'user_id',
        'conteudo',
        'anexo',
        'enviado_por_admin',
        'is_html',
    ];

    protected $casts = [
        'enviado_por_admin' => 'boolean',
        'is_html' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
