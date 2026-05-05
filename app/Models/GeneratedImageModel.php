<?php

namespace App\Models;

use CodeIgniter\Model;

class GeneratedImageModel extends Model
{
    protected $table            = 'generated_images';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id',
        'content_job_id',
        'provider_name',
        'source_url',
        'local_path',
        'credit_text',
        'license_info',
        'width',
        'height',
        'selected',
        'created_at',
    ];
}
