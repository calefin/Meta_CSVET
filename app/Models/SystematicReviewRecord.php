<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Um trabalho (título/resumo) importado das bases para a triagem.
 */
class SystematicReviewRecord extends Model
{
    public const DECISIONS = [
        'include' => 'Incluir',
        'exclude' => 'Excluir',
        'maybe' => 'Talvez',
    ];

    protected $fillable = [
        'systematic_review_id',
        'seq',
        'random_key',
        'dedupe_key',
        'title',
        'abstract',
        'authors',
        'year',
        'journal',
        'doi',
        'pmid',
        'url',
        'sources',
        'keywords',
        'extra',
        'ta_decision',
        'ta_consensus',
        'ta_reason',
        'ta_resolved_by_professor',
        'ft_decision',
        'ft_consensus',
        'ft_reason',
        'ft_resolved_by_professor',
        'resolution_note',
    ];

    protected $casts = [
        'extra' => 'array',
        'ta_resolved_by_professor' => 'boolean',
        'ft_resolved_by_professor' => 'boolean',
    ];

    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }

    public function decisions()
    {
        return $this->hasMany(SystematicReviewDecision::class);
    }

    /**
     * Chave de desduplicação: DOI > PMID > título normalizado (+ ano).
     */
    public static function dedupeKey(?string $doi, ?string $pmid, ?string $title, ?string $year = null): string
    {
        $doi = self::normalizeDoi($doi);
        if ($doi) {
            return hash('sha256', 'doi:' . $doi);
        }
        if (filled($pmid)) {
            return hash('sha256', 'pmid:' . trim($pmid));
        }

        return hash('sha256', 'title:' . self::normalizeTitle($title) . '|' . substr((string) $year, 0, 4));
    }

    public static function normalizeDoi(?string $doi): ?string
    {
        if (blank($doi)) {
            return null;
        }
        $doi = mb_strtolower(trim($doi));
        $doi = preg_replace('#^(https?://)?(dx\.)?doi\.org/#', '', $doi);
        $doi = preg_replace('/^doi:\s*/', '', $doi);

        return str_starts_with($doi, '10.') ? rtrim($doi, '.') : null;
    }

    public static function normalizeTitle(?string $title): string
    {
        return Str::of((string) $title)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }

    public function doiUrl(): ?string
    {
        return $this->doi ? 'https://doi.org/' . $this->doi : null;
    }

    public function link(): ?string
    {
        return $this->doiUrl()
            ?? ($this->pmid ? 'https://pubmed.ncbi.nlm.nih.gov/' . $this->pmid . '/' : null)
            ?? ($this->url ?: null);
    }

    public function citation(): string
    {
        $authors = Str::limit((string) $this->authors, 120);

        return trim(($authors ? $authors . '. ' : '') . ($this->year ? "({$this->year}) " : '') . ($this->journal ?: ''));
    }
}
