<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Ticket extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'titulo',
        'descricao',
        'status',
        'prioridade',
        'valor_orcado',
        'token',
        'archived_at',
        'orcamento_lembrete_enviado_at',
        'resgate_enviado_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'orcamento_lembrete_enviado_at' => 'datetime',
        'resgate_enviado_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::forceDeleting(function (Ticket $ticket) {
            foreach ($ticket->mensagens as $mensagem) {
                if ($mensagem->anexo) {
                    Storage::disk('public')->delete(preg_replace('#^storage/#', '', $mensagem->anexo));
                }
            }
            $ticket->mensagens()->delete();
        });
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function mensagens()
    {
        return $this->hasMany(Mensagem::class)->orderBy('created_at', 'asc');
    }

    public function ultimaMensagem()
    {
        return $this->hasOne(Mensagem::class)->latestOfMany();
    }

    public function clienteRespondeuDesde(\DateTimeInterface $referencia): bool
    {
        $ultima = $this->ultimaMensagem;

        return $ultima && !$ultima->enviado_por_admin && $ultima->created_at->greaterThan($referencia);
    }
}