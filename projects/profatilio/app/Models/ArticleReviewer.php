<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Participante (coautor/revisor) de um processo de revisão de artigo/livro.
 * A ordem de revisão (`position`) é independente da ordem de autoria
 * (`author_order`).
 */
class ArticleReviewer extends Model
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_DONE = 'done';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_REMOVED = 'removed';

    public const STATUS_LABELS = [
        self::STATUS_WAITING => 'Aguardando a vez',
        self::STATUS_IN_REVIEW => 'Na vez — revisando',
        self::STATUS_DONE => 'Revisão entregue',
        self::STATUS_SKIPPED => 'Pulado',
        self::STATUS_REMOVED => 'Removido do processo',
    ];

    /** Quem não conta mais na fila (mas continua no histórico). */
    public const INACTIVE_STATUSES = [self::STATUS_SKIPPED, self::STATUS_REMOVED];

    /** Taxonomia CRediT (Contributor Roles Taxonomy), usada pelas revistas. */
    public const CREDIT_ROLES = [
        'conceptualization' => 'Conceituação',
        'data_curation' => 'Curadoria de dados',
        'formal_analysis' => 'Análise formal',
        'funding_acquisition' => 'Obtenção de financiamento',
        'investigation' => 'Investigação',
        'methodology' => 'Metodologia',
        'project_administration' => 'Administração do projeto',
        'resources' => 'Recursos',
        'software' => 'Software',
        'supervision' => 'Supervisão',
        'validation' => 'Validação',
        'visualization' => 'Visualização',
        'writing_original' => 'Escrita – rascunho original',
        'writing_review' => 'Escrita – revisão e edição',
    ];

    public const CREDIT_DESCRIPTIONS = [
        'conceptualization' => 'Ideias; formulação dos objetivos e metas do trabalho.',
        'data_curation' => 'Organizar, anotar e manter os dados da pesquisa.',
        'formal_analysis' => 'Análises estatísticas, matemáticas ou computacionais dos dados.',
        'funding_acquisition' => 'Obter o financiamento (bolsa, edital, recurso) do projeto.',
        'investigation' => 'Executar experimentos, atendimentos, coletas de dados/amostras.',
        'methodology' => 'Desenhar ou desenvolver a metodologia.',
        'project_administration' => 'Gerenciar e coordenar a execução do trabalho.',
        'resources' => 'Fornecer materiais, reagentes, animais, pacientes, equipamentos ou laboratório.',
        'software' => 'Programação e desenvolvimento de software/códigos.',
        'supervision' => 'Orientação e supervisão das atividades.',
        'validation' => 'Verificar a reprodutibilidade e conferir os resultados.',
        'visualization' => 'Preparar figuras, gráficos, tabelas e imagens.',
        'writing_original' => 'Redigir o texto inicial (rascunho).',
        'writing_review' => 'Revisar criticamente, comentar e editar o texto.',
    ];

    /** Checklist que o participante marca ao confirmar a ficha. */
    public const CHECKLIST = [
        'name' => 'Conferi meu nome completo e o nome para citação exatamente como devem sair na publicação.',
        'email' => 'Meu e-mail está correto e é o que uso para correspondência acadêmica.',
        'phone' => 'Meu telefone (WhatsApp) está correto para contato urgente.',
        'affiliation' => 'Minha filiação está completa (instituição, departamento, cidade, estado e país).',
        'orcid' => 'Conferi meu ORCID (ou declarei que não possuo).',
        'roles' => 'As funções de participação marcadas correspondem ao que eu fiz no trabalho.',
        'conflict' => 'Não tenho conflito de interesses a declarar, ou informei no campo de observações.',
    ];

    protected $fillable = [
        'article_review_id',
        'user_id',
        'position',
        'author_order',
        'name',
        'email',
        'phone',
        'telegram_chat_id',
        'telegram_link_token',
        'review_days',
        'status',
        'token_hash',
        'token_encrypted',
        'sent_at',
        'due_at',
        'first_opened_at',
        'last_reminder_at',
        'overdue_notified',
        'returned_at',
        'version_number',
        'returned_file_path',
        'returned_filename',
        'comments',
        'suggestions',
        'participation_accepted_at',
        'participation_statement',
        'forensic',
        'citation_name',
        'institution',
        'department',
        'city',
        'state',
        'country',
        'orcid',
        'no_orcid',
        'contribution_roles',
        'contribution_other',
        'profile_notes',
        'consent',
        'profile_confirmed_at',
        'self_registered',
        'removed_at',
        'removed_reason',
    ];

    protected $hidden = ['token_hash', 'token_encrypted', 'telegram_link_token'];

    protected $attributes = [
        'status' => self::STATUS_WAITING,
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'due_at' => 'datetime',
        'first_opened_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'returned_at' => 'datetime',
        'participation_accepted_at' => 'datetime',
        'profile_confirmed_at' => 'datetime',
        'removed_at' => 'datetime',
        'overdue_notified' => 'boolean',
        'no_orcid' => 'boolean',
        'self_registered' => 'boolean',
        'forensic' => 'array',
        'consent' => 'array',
        'contribution_roles' => 'array',
        'token_encrypted' => 'encrypted',
    ];

    protected static function booted(): void
    {
        // Vincula automaticamente à conta (se houver) com o mesmo e-mail.
        static::saving(function (ArticleReviewer $reviewer) {
            if ($reviewer->isDirty('email')) {
                $reviewer->email = filled($reviewer->email) ? mb_strtolower(trim($reviewer->email)) : null;
                $reviewer->user_id = $reviewer->email
                    ? User::where('email', $reviewer->email)->value('id')
                    : null;
            }
        });
    }

    public function articleReview()
    {
        return $this->belongsTo(ArticleReview::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->hasMany(ArticleReviewFile::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isActive(): bool
    {
        return !in_array($this->status, self::INACTIVE_STATUSES, true);
    }

    /**
     * Link pessoal do participante. O banco guarda o hash (para busca) e uma
     * cópia criptografada com a APP_KEY, para o professor poder copiar/reenviar
     * o mesmo link sem invalidar o que a pessoa já tem. $rotate gera um novo e
     * desativa o anterior.
     */
    public function accessUrl(bool $rotate = false): string
    {
        $plain = $this->token_encrypted;

        if ($rotate || blank($plain) || blank($this->token_hash)) {
            $plain = $this->issueToken();
        }

        return route('article-review.show', ['token' => $plain]);
    }

    public function issueToken(): string
    {
        $plain = Str::random(64);
        $this->forceFill([
            'token_hash' => hash('sha256', $plain),
            'token_encrypted' => $plain,
        ])->save();

        return $plain;
    }

    public function revokeToken(): void
    {
        $this->forceFill(['token_hash' => null, 'token_encrypted' => null])->save();
    }

    public static function findByToken(string $plain): ?self
    {
        if (!preg_match('/^[A-Za-z0-9]{64}$/', $plain)) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $plain))->first();
    }

    public function initials(): string
    {
        $particles = ['de', 'da', 'do', 'das', 'dos', 'e', 'di', 'du', 'del', 'van', 'von'];
        $parts = preg_split('/\s+/', trim(Str::ascii($this->name))) ?: [];
        $parts = array_values(array_filter(
            $parts,
            fn ($p) => $p !== '' && !in_array(strtolower($p), $particles, true)
        ));

        if (empty($parts)) {
            return 'X';
        }

        $first = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $parts[0]), 0, 1));
        $last = count($parts) > 1 ? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', end($parts)), 0, 1)) : '';

        return ($first . $last) ?: 'X';
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_IN_REVIEW && $this->due_at && $this->due_at->isPast();
    }

    /**
     * Chat do Telegram para avisos: o vinculado neste processo pelo bot, ou o
     * da conta (orientando já vinculado no portal / admin).
     */
    public function telegramChatId(): ?string
    {
        if (filled($this->telegram_chat_id)) {
            return (string) $this->telegram_chat_id;
        }

        $user = $this->user_id ? $this->user : null;

        return $user?->telegram_chat_id ?: ($user?->student?->telegram_chat_id ?: null);
    }

    /** Dá para avisar por algum canal (e-mail ou Telegram)? */
    public function isReachable(): bool
    {
        return filled($this->email) || $this->telegramChatId() !== null;
    }

    /**
     * Link do bot para vincular o Telegram a este participante.
     */
    public function telegramLinkUrl(): ?string
    {
        $bot = ltrim((string) config('services.telegram.bot_username'), '@');
        if ($bot === '') {
            return null;
        }

        if (blank($this->telegram_link_token)) {
            $this->forceFill(['telegram_link_token' => Str::random(32)])->save();
        }

        return "https://t.me/{$bot}?start=rev_{$this->telegram_link_token}";
    }

    public function isProfileComplete(): bool
    {
        return $this->profile_confirmed_at !== null;
    }

    public function affiliationLine(): string
    {
        return collect([$this->department, $this->institution, $this->city, $this->state, $this->country])
            ->filter(fn ($v) => filled($v))
            ->implode(', ');
    }

    public function rolesLabel(): string
    {
        return collect($this->contribution_roles ?? [])
            ->map(fn ($r) => self::CREDIT_ROLES[$r] ?? null)
            ->filter()
            ->when(filled($this->contribution_other), fn ($c) => $c->push($this->contribution_other))
            ->implode('; ');
    }

    /**
     * Telefone só com dígitos e DDI (55 por padrão), para link do WhatsApp.
     */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);

        if (strlen($digits) < 10) {
            return null;
        }

        return strlen($digits) <= 11 ? '55' . $digits : $digits;
    }

    /**
     * Normaliza e valida ORCID (aceita URL ou só o número), conferindo o
     * dígito verificador ISO 7064 11-2. Retorna null se inválido.
     */
    public static function normalizeOrcid(?string $value): ?string
    {
        $raw = strtoupper(preg_replace('/[^0-9Xx]/', '', (string) $value));

        if (!preg_match('/^\d{15}[\dX]$/', $raw)) {
            return null;
        }

        $total = 0;
        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $raw[$i]) * 2;
        }
        $check = (12 - ($total % 11)) % 11;
        $expected = $check === 10 ? 'X' : (string) $check;

        if ($raw[15] !== $expected) {
            return null;
        }

        return implode('-', str_split($raw, 4));
    }
}
