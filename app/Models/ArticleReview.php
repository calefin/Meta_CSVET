<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleReview extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_REVIEWS_DONE = 'reviews_done';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_IN_REVIEW => 'Em Revisão',
        self::STATUS_REVIEWS_DONE => 'Revisões Concluídas',
        self::STATUS_SUBMITTED => 'Enviado à Revista (fechado)',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public const WORK_TYPES = [
        'article' => 'Artigo científico',
        'book' => 'Livro',
        'chapter' => 'Capítulo de livro',
        'abstract' => 'Resumo / Anais',
        'other' => 'Outro trabalho',
    ];

    /** Instruções padrão aos participantes (pré-preenchidas no cadastro). */
    public const DEFAULT_INSTRUCTIONS = "1. Antes de tudo, confira e confirme a sua ficha (nome, filiação, ORCID e tipo de participação).\n"
        . "2. Baixe a versão atual e trabalhe SEMPRE com \"Controlar Alterações\" ligado.\n"
        . "3. Corrija diretamente no texto; para dúvidas e sugestões maiores, use comentários no próprio arquivo.\n"
        . "4. Confira com atenção: seu nome e filiação, os dados e resultados da sua parte, as referências que você incluiu e a formatação.\n"
        . "5. Não aceite nem rejeite as alterações dos colegas anteriores — isso é feito na consolidação final.\n"
        . "6. Devolva dentro do prazo. Se não puder revisar agora, avise o Prof. Atilio para passarmos a vez sem atrasar o grupo.";

    protected $fillable = [
        'systematic_review_id',
        'title',
        'work_type',
        'article_type',
        'submission_checklist',
        'require_login',
        'notify_all',
        'signup_enabled',
        'version_restores',
        'short_name',
        'journal',
        'instructions',
        'original_file_path',
        'original_filename',
        'default_review_days',
        'status',
        'final_file_path',
        'final_filename',
        'started_at',
        'reviews_completed_at',
        'submitted_at',
        'notes',
        'history',
    ];

    protected $hidden = ['signup_token_hash', 'signup_token_encrypted'];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'work_type' => 'article',
    ];

    protected $casts = [
        'history' => 'array',
        'version_restores' => 'array',
        'submission_checklist' => 'array',
        'require_login' => 'boolean',
        'notify_all' => 'boolean',
        'signup_enabled' => 'boolean',
        'signup_token_encrypted' => 'encrypted',
        'started_at' => 'datetime',
        'reviews_completed_at' => 'datetime',
        'submitted_at' => 'date',
        'default_review_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (ArticleReview $review) {
            if (blank($review->short_name)) {
                $review->short_name = Str::limit($review->title, 60, '');
            }
        });

        // Arquivos ficam no disco local privado; excluir o registro limpa tudo.
        static::deleting(function (ArticleReview $review) {
            $disk = Storage::disk('local');
            $paths = $review->reviewers()->pluck('returned_file_path')
                ->merge($review->files()->pluck('path'))
                ->merge(collect($review->version_restores ?? [])->pluck('path'))
                ->push($review->original_file_path, $review->final_file_path)
                ->filter();

            foreach ($paths as $path) {
                $disk->delete($path);
            }
        });
    }

    /**
     * Registro de acompanhamento (linha do tempo) — tudo o que acontece no
     * fluxo: envios, aberturas, avisos, devoluções, prorrogações etc.
     */
    public function logEvent(string $event, ?string $details = null, bool $public = false): void
    {
        $history = $this->history ?? [];
        $history[] = [
            'at' => now()->format('Y-m-d H:i:s'),
            'event' => $event,
            'details' => $details,
            'actor' => auth()->check() ? auth()->user()->name : null,
            // Eventos públicos aparecem (só o título, sem detalhes/IP/e-mail)
            // na linha do tempo que todos os participantes veem.
            'public' => $public,
        ];

        $this->forceFill(['history' => $history])->saveQuietly();
    }

    public function reviewers()
    {
        return $this->hasMany(ArticleReviewer::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Participantes que ainda contam no processo (exclui removidos e pulados).
     */
    public function participants()
    {
        return $this->reviewers()->whereNotIn('status', ArticleReviewer::INACTIVE_STATUSES);
    }

    /** Projeto de revisão sistemática de origem (modelo "desenvolvimento completo"). */
    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }

    public function files()
    {
        return $this->hasMany(ArticleReviewFile::class)->orderBy('id');
    }

    /**
     * Medidas estimadas da versão atual (palavras, resumo, referências,
     * figuras/tabelas) para comparar com os limites do tipo de artigo.
     */
    public function manuscriptMetrics(): ?array
    {
        $current = $this->currentVersion();
        $disk = Storage::disk('local');
        if (blank($current['path']) || !$disk->exists($current['path'])) {
            return null;
        }
        $analysis = \App\Services\DocxInspector::analyze($disk->get($current['path']), pathinfo($current['filename'], PATHINFO_EXTENSION) ?: 'docx');
        if (!$analysis['readable']) {
            return null;
        }

        return \App\Support\ManuscriptMetrics::fromParagraphs($analysis['paragraphs']) + ['version' => $current['version']];
    }

    public function workTypeLabel(): string
    {
        return self::WORK_TYPES[$this->work_type] ?? 'Trabalho';
    }

    /**
     * Rótulo curto para textos ("o artigo", "o livro"...).
     */
    public function workNoun(): string
    {
        return match ($this->work_type) {
            'book' => 'livro',
            'chapter' => 'capítulo',
            'abstract' => 'resumo',
            'other' => 'trabalho',
            default => 'artigo',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_CANCELLED], true);
    }

    /**
     * Link de cadastro do processo (para o professor compartilhar no
     * particular). Fica guardado criptografado para poder ser copiado de novo
     * a qualquer momento; "gerar novo" invalida o anterior.
     */
    public function signupUrl(bool $rotate = false): string
    {
        $plain = $this->signup_token_encrypted;

        if ($rotate || blank($plain)) {
            $plain = Str::random(64);
            $this->forceFill([
                'signup_token_hash' => hash('sha256', $plain),
                'signup_token_encrypted' => $plain,
            ])->saveQuietly();
        }

        return route('article-review.signup', ['token' => $plain]);
    }

    public static function findBySignupToken(string $plain): ?self
    {
        if (!preg_match('/^[A-Za-z0-9]{64}$/', $plain)) {
            return null;
        }

        return static::where('signup_token_hash', hash('sha256', $plain))->first();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function activeReviewer(): ?ArticleReviewer
    {
        return $this->reviewers->firstWhere('status', ArticleReviewer::STATUS_IN_REVIEW);
    }

    /**
     * Última versão disponível: a mais alta entre as devolvidas pelos
     * revisores e as restaurações feitas pelo professor; o original se
     * ninguém devolveu nada ainda.
     *
     * @return array{path: string, filename: string, version: int}
     */
    public function currentVersion(): array
    {
        $versions = $this->allVersions();

        return $versions[count($versions) - 1];
    }

    /**
     * Todas as versões, da v1 (original) à mais recente.
     *
     * @return array<int, array{key: string, path: string, filename: string, version: int, label: string, at: ?\Illuminate\Support\Carbon}>
     */
    public function allVersions(): array
    {
        $versions = [[
            'key' => 'original',
            'path' => $this->original_file_path,
            'filename' => $this->baseFileStem() . '_v1.' . $this->originalExtension(),
            'version' => 1,
            'label' => 'Original',
            'at' => $this->created_at,
        ]];

        $returned = $this->reviewers()
            ->where('status', ArticleReviewer::STATUS_DONE)
            ->whereNotNull('returned_file_path')
            ->get();

        foreach ($returned as $r) {
            $versions[] = [
                'key' => (string) $r->id,
                'path' => $r->returned_file_path,
                'filename' => $r->returned_filename,
                'version' => (int) $r->version_number,
                'label' => 'Revisão de ' . $r->name,
                'at' => $r->returned_at,
            ];
        }

        foreach ($this->version_restores ?? [] as $i => $restore) {
            $versions[] = [
                'key' => 'restore-' . $i,
                'path' => $restore['path'],
                'filename' => $restore['filename'],
                'version' => (int) $restore['version'],
                'label' => 'Restaurada da v' . $restore['from_version'],
                'at' => \Illuminate\Support\Carbon::parse($restore['at']),
            ];
        }

        usort($versions, fn ($a, $b) => $a['version'] <=> $b['version']);

        return $versions;
    }

    public function findVersion(string $key): ?array
    {
        foreach ($this->allVersions() as $v) {
            if ($v['key'] === $key) {
                return $v;
            }
        }

        return null;
    }

    public function originalExtension(): string
    {
        $ext = strtolower(pathinfo($this->original_filename ?: $this->original_file_path, PATHINFO_EXTENSION));

        return in_array($ext, ['docx', 'doc', 'odt'], true) ? $ext : 'docx';
    }

    /**
     * Prefixo do nome de arquivo, derivado do nome curto do artigo, só com
     * ASCII seguro (nada de barras, pontos ou caracteres de controle).
     */
    public function baseFileStem(): string
    {
        $stem = Str::of($this->short_name ?: $this->title)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_')
            ->limit(50, '')
            ->trim('_')
            ->toString();

        return $stem !== '' ? $stem : 'Artigo';
    }
}
