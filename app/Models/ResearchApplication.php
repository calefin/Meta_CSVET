<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inscrição de um aluno numa chamada de seleção. O aluno acompanha o
 * andamento por um link pessoal (só o hash do token fica no banco).
 */
class ResearchApplication extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_INTERVIEWED = 'interviewed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_WAITLIST = 'waitlist';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_REMOVED = 'removed';

    public const STATUS_LABELS = [
        self::STATUS_NEW => 'Inscrição recebida',
        self::STATUS_INTERVIEW => 'Reunião marcada',
        self::STATUS_INTERVIEWED => 'Reunião feita — aguardando resultado',
        self::STATUS_APPROVED => 'Aprovado(a)',
        self::STATUS_WAITLIST => 'Lista de espera',
        self::STATUS_REJECTED => 'Não aprovado(a)',
        self::STATUS_WITHDRAWN => 'Desistiu',
        self::STATUS_REMOVED => 'Desligado(a) do projeto',
    ];

    public const STATUS_COLORS = [
        self::STATUS_NEW => 'warning',
        self::STATUS_INTERVIEW => 'info',
        self::STATUS_INTERVIEWED => 'primary',
        self::STATUS_APPROVED => 'success',
        self::STATUS_WAITLIST => 'gray',
        self::STATUS_REJECTED => 'danger',
        self::STATUS_WITHDRAWN => 'gray',
        self::STATUS_REMOVED => 'danger',
    ];

    public const PURPOSES = [
        'career' => 'Currículo para Residência, Mestrado ou Doutorado',
        'publication' => 'Publicar artigos científicos',
        'learning' => 'Aprender metodologia de pesquisa',
        'other' => 'Outro objetivo',
    ];

    public const ENGLISH_LEVELS = [
        'translator' => 'Leio artigos com ajuda de tradutor',
        'intermediate' => 'Leio artigos sem muita dificuldade',
        'advanced' => 'Leio e escrevo em inglês com facilidade',
    ];

    protected $fillable = [
        'research_call_id',
        'user_id',
        'access_token_hash',
        'access_token',
        'name',
        'email',
        'phone',
        'course',
        'institution',
        'semester',
        'purpose',
        'axis_preferences',
        'project_preferences',
        'weekly_hours',
        'english_level',
        'lattes_url',
        'orcid',
        'motivation',
        'experience',
        'declarations',
        'adhesion_text',
        'adhesion_sha256',
        'adhesion_accepted_at',
        'email_verified_at',
        'status',
        'interview_at',
        'interview_place',
        'interview_answers',
        'interview_notes',
        'lead_project_code',
        'reviewer_project_code',
        'decision_message',
        'decided_at',
        'history',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'axis_preferences' => 'array',
        'project_preferences' => 'array',
        'declarations' => 'array',
        'interview_answers' => 'array',
        'history' => 'array',
        'interview_at' => 'datetime',
        'decided_at' => 'datetime',
        'adhesion_accepted_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'semester' => 'integer',
        'weekly_hours' => 'integer',
        'access_token' => 'encrypted',
    ];

    protected $hidden = ['access_token_hash', 'access_token'];

    public function researchCall()
    {
        return $this->belongsTo(ResearchCall::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Liga a inscrição à conta criada depois da aprovação (pelo convite). Sem isso a ficha
     * seguia mostrando "Sem conta" para quem já tinha conta e até termo assinado.
     * Só depois de o e-mail ser validado, como em SystematicReviewScreener::linkVerifiedUser.
     */
    public static function linkVerifiedUser(User $user): void
    {
        if (!in_array($user->role, ['student', 'admin'], true)) {
            return;
        }
        static::whereNull('user_id')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $user->email))])
            ->update(['user_id' => $user->id]);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('access_token_hash', static::hashToken($token))->first();
    }

    public function trackingUrl(): ?string
    {
        return $this->access_token ? route('research-call.track', $this->access_token) : null;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? (string) $this->purpose;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN, self::STATUS_REMOVED], true);
    }

    public function logEvent(string $event, ?string $detail = null): void
    {
        $history = $this->history ?? [];
        $history[] = ['at' => now()->toIso8601String(), 'event' => $event, 'detail' => $detail];
        $this->history = $history;
        $this->save();
    }

    /** Nome como no cadastro de usuários: "JULIA ZAGOLIN COSTA" → "Julia Zagolin Costa". */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = User::normalizeName($value);
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }
}
