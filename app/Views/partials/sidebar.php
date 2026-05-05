<?php
$currentUser = auth()->user();
$currentUrl  = uri_string();

/**
 * Helper untuk cek apakah menu aktif
 */
function isMenuActive(string $path): string {
    $currentUrl = uri_string();
    return (strpos($currentUrl, $path) !== false) ? 'active' : '';
}

function isDropdownActive(array $paths): string {
    $currentUrl = uri_string();
    foreach ($paths as $path) {
        if (strpos($currentUrl, $path) !== false) {
            return 'active';
        }
    }
    return '';
}
?>
<div class="main-sidebar sidebar-style-1">
  <aside id="sidebar-wrapper">
    <div class="sidebar-brand">
      <a href="<?= base_url('dashboard') ?>"><?= esc(setting('App.siteName') ?? 'CI4 RBAC') ?></a>
    </div>
    <div class="sidebar-brand sidebar-brand-sm">
      <a href="<?= base_url('dashboard') ?>"><?= esc(setting('App.siteNameShort') ?? 'C4') ?></a>
    </div>
    <ul class="sidebar-menu">

      <!-- Dashboard -->
      <li class="menu-header">Dashboard</li>
      <li class="<?= isMenuActive('dashboard') && !str_contains($currentUrl, 'admin') ? 'active' : '' ?>">
        <a class="nav-link" href="<?= base_url('dashboard') ?>"><i class="fas fa-fire"></i> <span>Dashboard</span></a>
      </li>

      <!-- Admin Menu (hanya untuk active group yang punya akses admin) -->
      <?php if (activeGroupCan('admin.access')): ?>
      <li class="menu-header">Administrasi</li>

      <!-- User Management -->
      <?php if (activeGroupCan('users.list')): ?>
      <li class="<?= isMenuActive('admin/users') ?>">
        <a class="nav-link" href="<?= base_url('admin/users') ?>"><i class="fas fa-users"></i> <span>Manajemen User</span></a>
      </li>
      <?php endif; ?>

      <!-- Role Management (superadmin only) -->
      <?php if (activeGroupIs('superadmin')): ?>
      <li class="nav-item dropdown <?= isDropdownActive(['admin/roles']) ?>">
        <a href="#" class="nav-link has-dropdown"><i class="fas fa-user-shield"></i> <span>Role & Permission</span></a>
        <ul class="dropdown-menu">
          <li class="<?= isMenuActive('admin/roles') && !str_contains($currentUrl, 'permissions') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= base_url('admin/roles') ?>">Daftar Role</a>
          </li>
          <li class="<?= isMenuActive('admin/roles/permissions') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= base_url('admin/roles/permissions') ?>">Permission Matrix</a>
          </li>
        </ul>
      </li>
      <?php endif; ?>

      <!-- Settings -->
      <?php if (activeGroupCan('admin.settings')): ?>
      <li class="<?= isMenuActive('admin/settings') ?>">
        <a class="nav-link" href="<?= base_url('admin/settings') ?>"><i class="fas fa-cog"></i> <span>Pengaturan</span></a>
      </li>
      <?php endif; ?>

      <!-- Content Generator -->
      <?php if (activeGroupCan('content.generate') || activeGroupCan('content.view_history')): ?>
      <li class="nav-item dropdown <?= isDropdownActive(['admin/content']) ?>">
        <a href="#" class="nav-link has-dropdown"><i class="fas fa-pen-nib"></i> <span>Content Generator</span></a>
        <ul class="dropdown-menu">
          <?php if (activeGroupCan('content.generate')): ?>
          <li class="<?= isMenuActive('admin/content') && !str_contains($currentUrl, 'history') && !str_contains($currentUrl, 'detail') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= base_url('admin/content') ?>">Generate Baru</a>
          </li>
          <?php endif; ?>
          <?php if (activeGroupCan('content.view_history')): ?>
          <li class="<?= isMenuActive('admin/content/history') || isMenuActive('admin/content/detail') ? 'active' : '' ?>">
            <a class="nav-link" href="<?= base_url('admin/content/history') ?>">Riwayat Job</a>
          </li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Profil -->
      <li class="menu-header">Akun</li>
      <li class="<?= isMenuActive('profile') ?>">
        <a class="nav-link" href="<?= base_url('profile') ?>"><i class="far fa-user"></i> <span>Profil Saya</span></a>
      </li>
      <li>
        <a class="nav-link text-danger" href="<?= base_url('logout') ?>"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
      </li>

    </ul>
  </aside>
</div>
