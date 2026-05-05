<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPromptFieldsToContentJobs extends Migration
{
    public function up()
    {
        $this->forge->addColumn('content_jobs', [
            'source_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'youtube',
                'after' => 'user_id',
            ],
            'prompt_text' => [
                'type' => 'LONGTEXT',
                'null' => true,
                'after' => 'source_description',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('content_jobs', ['source_type', 'prompt_text']);
    }
}
