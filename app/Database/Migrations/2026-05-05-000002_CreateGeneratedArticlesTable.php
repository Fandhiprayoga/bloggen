<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGeneratedArticlesTable extends Migration
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
            'article_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'article_body_html' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'article_body_markdown' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'word_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'language' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'id',
            ],
            'tone' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'santai-edukatif',
            ],
            'primary_keyword' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'secondary_keywords_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'seo_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'seo_meta_description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'seo_slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'faq_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'seo_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
            ],
            'is_edited_manually' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
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
        $this->forge->addUniqueKey('content_job_id');
        $this->forge->addKey('seo_slug');
        $this->forge->addForeignKey('content_job_id', 'content_jobs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('generated_articles');
    }

    public function down()
    {
        $this->forge->dropTable('generated_articles', true);
    }
}
