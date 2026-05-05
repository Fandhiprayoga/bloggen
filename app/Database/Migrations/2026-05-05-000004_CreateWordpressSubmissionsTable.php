<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWordpressSubmissionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
            ],
            'content_job_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => false,
            ],
            'wp_site_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'wp_post_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'wp_post_url' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'wp_media_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'wp_status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'failed'],
                'default'    => 'draft',
            ],
            'wp_category_ids_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'wp_tag_ids_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'request_snapshot_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'response_snapshot_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'submitted_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'submitted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('content_job_id');
        $this->forge->addKey('wp_status');
        $this->forge->addForeignKey('content_job_id', 'content_jobs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('submitted_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('wordpress_submissions');
    }

    public function down()
    {
        $this->forge->dropTable('wordpress_submissions', true);
    }
}
