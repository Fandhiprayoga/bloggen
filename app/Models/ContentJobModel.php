<?php

namespace App\Models;

use CodeIgniter\Model;

class ContentJobModel extends Model
{
    protected $table            = 'content_jobs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'id',
        'user_id',
        'source_type',
        'youtube_url',
        'youtube_video_id',
        'source_title',
        'source_description',
        'prompt_text',
        'source_comments_json',
        'transcript_text',
        'status',
        'error_code',
        'error_message',
        'created_at',
        'updated_at',
    ];
}
