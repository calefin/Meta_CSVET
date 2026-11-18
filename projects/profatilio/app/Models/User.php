<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'must_change_password',
        'telegram_chat_id',
        'telegram_link_token',
        'profile_photo_path',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->role === 'admin';
        }

        if ($panel->getId() === 'portal') {
            // 'inactive' mantém acesso de leitura ao portal por design (ver
            // PROJECT_MEMORY.md item 40) — só fica de fora de notificações
            // burocráticas. Só 'awaiting_email_validation' e
            // 'awaiting_advisor_approval' bloqueiam o acesso de fato.
            return ($this->role === 'student' && $this->student && in_array($this->student->status, ['active', 'inactive'], true)) || $this->role === 'admin';
        }

        if ($panel->getId() === 'revisor') {
            // Área do Revisor: conta de revisor ou qualquer pessoa vinculada a
            // um processo de revisão de artigo/livro (inclusive alunos).
            return $this->role === 'reviewer'
                || $this->role === 'admin'
                || \App\Models\ArticleReviewer::where('user_id', $this->id)->exists();
        }

        return false;
    }

    public function getNameAttribute($value)
    {
        if (auth()->check() && auth()->user()->role === 'student') {
            $parts = explode(' ', trim($value));
            return count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $value;
        }
        return $value;
    }

    /**
     * Normaliza nomes de pessoas para Title Case, mantendo preposições e conjunções em minúsculas.
     */
    public static function normalizeName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        // Converte toda a string para minúsculas, lidando corretamente com acentuação UTF-8
        $name = mb_strtolower(trim($name), 'UTF-8');

        // Divide o nome por espaços
        $words = explode(' ', $name);
        $prepositions = ['de', 'da', 'do', 'dos', 'das', 'e'];

        $normalizedWords = [];
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            if (in_array($word, $prepositions, true)) {
                $normalizedWords[] = $word;
            } elseif (preg_match('/^(ii|iii|iv)$/', $word)) {
                // Sufixos em algarismos romanos: "Neto II", não "Neto Ii".
                $normalizedWords[] = mb_strtoupper($word, 'UTF-8');
            } else {
                // Suporte para d'Ávila, d'Este, etc.
                if (preg_match('/^([a-z])\'([a-z].*)$/ui', $word, $matches)) {
                    $prefix = mb_strtolower($matches[1], 'UTF-8');
                    $subword = $matches[2];
                    $subFirstLetter = mb_substr($subword, 0, 1, 'UTF-8');
                    $subRemainder = mb_substr($subword, 1, null, 'UTF-8');
                    $normalizedWords[] = $prefix . "'" . mb_strtoupper($subFirstLetter, 'UTF-8') . $subRemainder;
                } else {
                    $firstLetter = mb_substr($word, 0, 1, 'UTF-8');
                    $remainder = mb_substr($word, 1, null, 'UTF-8');
                    $normalizedWords[] = mb_strtoupper($firstLetter, 'UTF-8') . $remainder;
                }
            }
        }

        return implode(' ', $normalizedWords);
    }

    /**
     * Mutator para normalizar automaticamente o nome ao salvar no banco de dados.
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = self::normalizeName($value);
    }

    /**
     * Retorna a URL do avatar do usuário para o painel Filament.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->profile_photo_path
            ? asset('storage/' . $this->profile_photo_path)
            : null;
    }
}
