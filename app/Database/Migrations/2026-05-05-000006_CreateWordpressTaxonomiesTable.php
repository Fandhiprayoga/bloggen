<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWordpressTaxonomiesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'taxonomy' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],
            'wp_term_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'slug' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'post_count' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ],
            'synced_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey(['taxonomy', 'wp_term_id'], false, true);
        $this->forge->addKey('name');
        $this->forge->createTable('wordpress_taxonomies');
    }

    public function down()
    {
        $this->forge->dropTable('wordpress_taxonomies', true);
    }
}
