<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Mensagem da conversa registrada de um projeto (professor + dupla). */
class SrMessage extends Model
{
    public const ROLE_PROFESSOR = 'professor';
    public const ROLE_STUDENT = 'aluno';

    protected $table = 'sr_messages';

    protected $fillable = ['systematic_review_id', 'user_id', 'author_name', 'author_role', 'body'];

    protected $casts = ['seen_at' => 'datetime'];

    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
