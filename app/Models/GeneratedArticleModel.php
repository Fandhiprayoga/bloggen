<?php

namespace App\Models;

use CodeIgniter\Model;

class GeneratedArticleModel extends Model
{
    protected $table            = 'generated_articles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'id',
        'content_job_id',
        'article_title',
        'article_body_html',
        'article_body_markdown',
        'word_count',
        'language',
        'tone',
        'primary_keyword',
        'secondary_keywords_json',
        'seo_title',
        'seo_meta_description',
        'seo_slug',
        'faq_json',
        'seo_score',
        'is_edited_manually',
        'created_at',
        'updated_at',
    ];
}
