<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     */
    public string $defaultGroup = 'user';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Super Admin',
            'description' => 'Kontrol penuh terhadap seluruh sistem.',
        ],
        'admin' => [
            'title'       => 'Admin',
            'description' => 'Administrator harian sistem.',
        ],
        'manager' => [
            'title'       => 'Manager',
            'description' => 'Manajer yang dapat melihat laporan dan mengelola data.',
        ],
        'user' => [
            'title'       => 'User',
            'description' => 'Pengguna umum dengan akses terbatas.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     */
    public array $permissions = [
        // Admin area
        'admin.access'        => 'Dapat mengakses area admin',
        'admin.settings'      => 'Dapat mengakses pengaturan sistem',

        // User management
        'users.list'          => 'Dapat melihat daftar pengguna',
        'users.create'        => 'Dapat membuat pengguna baru',
        'users.edit'          => 'Dapat mengedit pengguna',
        'users.delete'        => 'Dapat menghapus pengguna',
        'users.manage-roles'  => 'Dapat mengatur role pengguna',

        // Role management
        'roles.list'          => 'Dapat melihat daftar role',
        'roles.create'        => 'Dapat membuat role baru',
        'roles.edit'          => 'Dapat mengedit role',
        'roles.delete'        => 'Dapat menghapus role',

        // Dashboard
        'dashboard.access'    => 'Dapat mengakses dashboard',
        'dashboard.stats'     => 'Dapat melihat statistik',

        // Reports
        'reports.view'        => 'Dapat melihat laporan',
        'reports.export'      => 'Dapat mengekspor laporan',

        // Content generation
        'content.generate'    => 'Dapat membuat konten dari YouTube',
        'content.review'      => 'Dapat meninjau dan mengedit hasil konten',
        'content.submit_wp'   => 'Dapat mengirim draft ke WordPress',
        'content.view_history' => 'Dapat melihat riwayat generate konten',
        'content.manage_settings' => 'Dapat mengelola pengaturan integrasi konten',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     */
    public array $matrix = [
        'superadmin' => [
            'admin.*',
            'users.*',
            'roles.*',
            'dashboard.*',
            'reports.*',
            'content.*',
        ],
        'admin' => [
            'admin.access',
            'users.list',
            'users.create',
            'users.edit',
            'users.delete',
            'dashboard.*',
            'reports.*',
            'content.*',
        ],
        'manager' => [
            'admin.access',
            'users.list',
            'dashboard.*',
            'reports.*',
            'content.generate',
            'content.review',
            'content.view_history',
        ],
        'user' => [
            'dashboard.access',
        ],
    ];
}
