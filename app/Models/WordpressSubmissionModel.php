<?php

namespace App\Models;

use CodeIgniter\Model;

class WordpressSubmissionModel extends Model
{
    protected $table            = 'wordpress_submissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id',
        'content_job_id',
        'wp_site_key',
        'wp_post_id',
        'wp_post_url',
        'wp_media_id',
        'wp_status',
        'wp_category_ids_json',
        'wp_tag_ids_json',
        'request_snapshot_json',
        'response_snapshot_json',
        'submitted_by',
        'submitted_at',
    ];
}
