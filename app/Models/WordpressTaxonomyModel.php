<?php

namespace App\Models;

use CodeIgniter\Model;

class WordpressTaxonomyModel extends Model
{
    protected $table         = 'wordpress_taxonomies';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'taxonomy',
        'wp_term_id',
        'name',
        'slug',
        'description',
        'post_count',
        'synced_at',
        'created_at',
        'updated_at',
    ];
}
