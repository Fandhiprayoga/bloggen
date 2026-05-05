<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ---------------------------------------------------------------
// Auth Routes (Shield)
// ---------------------------------------------------------------
service('auth')->routes($routes);

// ---------------------------------------------------------------
// Public Routes
// ---------------------------------------------------------------
$routes->get('/', 'AuthController::login');
$routes->get('maintenance', static function () {
    return view('errors/maintenance');
});

// ---------------------------------------------------------------
// Protected Routes (require login)
// ---------------------------------------------------------------
$routes->group('', ['filter' => 'session'], static function ($routes) {

    // Dashboard
    $routes->get('dashboard', 'DashboardController::index');

    // Switch Active Group
    $routes->post('switch-group', 'GroupSwitchController::switch');

    // Profile
    $routes->get('profile', 'ProfileController::index');
    $routes->post('profile/update', 'ProfileController::update');

    // ---------------------------------------------------------------
    // Admin Routes (require admin.access permission)
    // ---------------------------------------------------------------
    $routes->group('admin', ['filter' => 'permission:admin.access'], static function ($routes) {

        // User Management
        $routes->group('users', static function ($routes) {
            $routes->get('/', 'UserController::index', ['filter' => 'permission:users.list']);
            $routes->get('create', 'UserController::create', ['filter' => 'permission:users.create']);
            $routes->post('store', 'UserController::store', ['filter' => 'permission:users.create']);
            $routes->get('edit/(:num)', 'UserController::edit/$1', ['filter' => 'permission:users.edit']);
            $routes->post('update/(:num)', 'UserController::update/$1', ['filter' => 'permission:users.edit']);
            $routes->post('delete/(:num)', 'UserController::delete/$1', ['filter' => 'permission:users.delete']);
            $routes->post('assign-role/(:num)', 'UserController::assignRole/$1', ['filter' => 'permission:users.manage-roles']);
        });

        // Role Management (superadmin only)
        $routes->group('roles', ['filter' => 'role:superadmin'], static function ($routes) {
            $routes->get('/', 'RoleController::index');
            $routes->get('permissions', 'RoleController::permissions');
        });

        // Settings
        $routes->group('settings', ['filter' => 'permission:admin.settings'], static function ($routes) {
            $routes->get('/', 'SettingController::index');
            $routes->post('update/general', 'SettingController::updateGeneral');
            $routes->post('update/branding', 'SettingController::updateBranding');
            $routes->post('update/appearance', 'SettingController::updateAppearance');
            $routes->post('update/auth', 'SettingController::updateAuth');
            $routes->post('update/mail', 'SettingController::updateMail');
            $routes->post('test-email', 'SettingController::testEmail');
            $routes->post('reset', 'SettingController::resetDefaults');
        });

        // Content Generator
        $routes->group('content', static function ($routes) {
            $routes->get('/', 'ContentController::index', ['filter' => 'permission:content.generate']);
            $routes->post('generate', 'ContentController::generate', ['filter' => 'permission:content.generate']);
            $routes->get('history', 'ContentController::history', ['filter' => 'permission:content.view_history']);
            $routes->get('detail/(:segment)', 'ContentController::detail/$1', ['filter' => 'permission:content.view_history']);
            $routes->post('publish/(:segment)', 'ContentController::publish/$1', ['filter' => 'permission:content.submit_wp']);
            $routes->post('sync-taxonomies/(:segment)', 'ContentController::syncTaxonomies/$1', ['filter' => 'permission:content.submit_wp']);
            $routes->post('create-category/(:segment)', 'ContentController::createCategory/$1', ['filter' => 'permission:content.submit_wp']);
            $routes->post('create-tag/(:segment)', 'ContentController::createTag/$1', ['filter' => 'permission:content.submit_wp']);
            $routes->post('regenerate-text/(:segment)', 'ContentController::regenerateText/$1', ['filter' => 'permission:content.generate']);
            $routes->post('generate-image/(:segment)', 'ContentController::generateImage/$1', ['filter' => 'permission:content.generate']);
            $routes->post('upload-image/(:segment)', 'ContentController::uploadImage/$1', ['filter' => 'permission:content.generate']);
            $routes->get('check-ollama', 'ContentController::checkOllama', ['filter' => 'permission:content.generate']);
        });
    });
});
