<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContentJobsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'youtube_url' => [
                'type'       => 'TEXT',
                'null'       => false,
            ],
            'youtube_video_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
            ],
            'source_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'source_description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'source_comments_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'transcript_text' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['queued', 'processing', 'ready', 'failed', 'submitted'],
                'default'    => 'queued',
            ],
            'error_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('youtube_video_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('content_jobs');
    }

    public function down()
    {
        $this->forge->dropTable('content_jobs', true);
    }
}
