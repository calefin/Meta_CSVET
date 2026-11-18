<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;

/**
 * Login do orientando/aluno do módulo de gestão — email+senha própria,
 * independente do ProvaGen (decisão do projeto: só o professor usa SSO).
 * Substitui a antiga tabela `users` (role=student) do profatilio original;
 * o nome mudou para não colidir com a `users` do ProvaGen, que agora divide
 * o mesmo banco.
 */
class AdviseeAccount extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, BelongsToOwner;

    protected $fillable = [
        'owner_user_id',
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

    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        return $this->hasOne(Student::class, 'user_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'portal') {
            return $this->role === 'student' && $this->student && in_array($this->student->status, ['active', 'inactive'], true);
        }

        return false;
    }

    /**
     * TODO: o profatilio original abreviava nomes exibidos quando o viewer
     * autenticado era um aluno (auth()->user()->role === 'student'), como
     * medida de privacidade entre orientandos. Isso dependia de "o usuário
     * logado" ser sempre resolvível por auth()->user() — agora que professor
     * (guard admin) e aluno (guard portal) são modelos/tabelas separados,
     * essa checagem precisa ser refeita explicitamente contra o guard
     * 'portal' antes de reativar a abreviação. Deixado passando o nome
     * completo por enquanto para não silenciosamente esconder um
     * comportamento que precisa de revisão.
     */
    public function getNameAttribute($value)
    {
        return $value;
    }

    public static function normalizeName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        $name = mb_strtolower(trim($name), 'UTF-8');

        $words = explode(' ', $name);
        $prepositions = ['de', 'da', 'do', 'dos', 'das', 'e'];

        $normalizedWords = [];
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            if (in_array($word, $prepositions, true)) {
                $normalizedWords[] = $word;
            } else {
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

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = self::normalizeName($value);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->profile_photo_path
            ? asset('storage/' . $this->profile_photo_path)
            : null;
    }
}
