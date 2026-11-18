<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'cpf_cnpj',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function whatsappUrl(string $mensagem): ?string
    {
        if (!$this->telefone) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $this->telefone);

        if (!$digitos) {
            return null;
        }

        if (!str_starts_with($digitos, '55')) {
            $digitos = '55' . $digitos;
        }

        return "https://wa.me/{$digitos}?text=" . rawurlencode($mensagem);
    }
}