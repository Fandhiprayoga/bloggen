<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGeneratedImagesTable extends Migration
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
            'provider_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'source_url' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'local_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'credit_text' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'license_info' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'width' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'height' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'selected' => [
                'type'    => 'BOOLEAN',
                'default' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('content_job_id');
        $this->forge->addKey('selected');
        $this->forge->addForeignKey('content_job_id', 'content_jobs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('generated_images');
    }

    public function down()
    {
        $this->forge->dropTable('generated_images', true);
    }
}
