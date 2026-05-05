<?php
/** @var list<array<string, mixed>> $jobs */
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h4>Riwayat Generate Konten</h4>
        <div class="card-header-action">
          <?php if (activeGroupCan('content.generate')): ?>
          <a href="<?= base_url('admin/content') ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Job Baru
          </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>Job ID</th>
                <th>Source</th>
                <th>Prompt Ringkas</th>
                <th>Status</th>
                <th>Error</th>
                <th>Dibuat</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (! empty($jobs)): ?>
                <?php foreach ($jobs as $job): ?>
                  <tr>
                    <td><?= esc(substr((string) ($job['id'] ?? ''), 0, 8)) ?>...</td>
                    <td><?= esc((string) ($job['source_type'] ?? 'youtube')) ?></td>
                    <td class="text-truncate" style="max-width: 260px;"><?= esc((string) ($job['prompt_text'] ?? '-')) ?></td>
                    <td>
                      <?php
                        $badgeClass = match($job['status']) {
                          'ready' => 'badge-success',
                          'processing' => 'badge-info',
                          'failed' => 'badge-danger',
                          'submitted' => 'badge-primary',
                          default => 'badge-secondary',
                        };
                      ?>
                      <span class="badge <?= $badgeClass ?>"><?= esc((string) ($job['status'] ?? '')) ?></span>
                    </td>
                    <td>
                      <?php if (! empty($job['error_code'])): ?>
                        <span class="text-danger"><?= esc((string) ($job['error_code'] ?? '')) ?></span>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                    <td><?= esc((string) ($job['created_at'] ?? '-')) ?></td>
                    <td>
                      <a href="<?= base_url('admin/content/detail/' . $job['id']) ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center">Belum ada data job.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
