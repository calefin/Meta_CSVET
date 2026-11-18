<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Documento complementar de um processo de revisão (figuras, tabelas, normas
 * da revista, carta de apresentação...). Sem article_reviewer_id = enviado
 * pelo professor.
 */
class ArticleReviewFile extends Model
{
    protected $fillable = [
        'article_review_id',
        'article_reviewer_id',
        'path',
        'filename',
        'description',
        'size_kb',
        'sha256',
        'uploaded_by',
    ];

    public function articleReview()
    {
        return $this->belongsTo(ArticleReview::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(ArticleReviewer::class, 'article_reviewer_id');
    }
}
