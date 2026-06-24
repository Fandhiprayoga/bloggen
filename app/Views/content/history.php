<?php
/** @var list<array<string, mixed>> $jobs */
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h4>Riwayat Generate Konten</h4>
        <div class="card-header-action">
          <?php if (activeGroupCan('content.submit_wp')): ?>
          <button type="button" class="btn btn-outline-success btn-sm mr-2" id="btnCheckWp">
            <i class="fas fa-plug mr-1"></i> Check WP Koneksi
          </button>
          <?php endif; ?>
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
    <div id="wpConnectionResult" class="card d-none mt-3">
      <div class="card-header">
        <h4 class="mb-0" id="wpConnectionTitle">WordPress Connection</h4>
      </div>
      <div class="card-body">
        <div id="wpConnectionBody"></div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('btnCheckWp');
  if (!btn) return;
  const resultCard = document.getElementById('wpConnectionResult');
  const resultBody = document.getElementById('wpConnectionBody');
  const resultTitle = document.getElementById('wpConnectionTitle');

  btn.addEventListener('click', function () {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Checking...';
    resultCard.classList.remove('d-none');
    resultBody.innerHTML = '<span class="text-muted">Menghubungi WordPress API...</span>';
    resultTitle.textContent = 'WordPress Connection';

    fetch('<?= base_url('admin/content/check-wordpress') ?>')
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.ok) {
          resultTitle.innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i> WordPress Terhubung';
          resultBody.innerHTML =
            '<div class="alert alert-success mb-0">' +
            '<strong>' + escHtml(data.message) + '</strong>' +
            (data.data && data.data.wp_url ? '<br><small>URL: ' + escHtml(data.data.wp_url) + '</small>' : '') +
            '</div>';
        } else {
          resultTitle.innerHTML = '<i class="fas fa-times-circle text-danger mr-1"></i> WordPress Gagal Koneksi';
          var code = data.error_code ? '<code>' + escHtml(data.error_code) + '</code> — ' : '';
          resultBody.innerHTML =
            '<div class="alert alert-danger mb-0">' +
            '<strong>Error:</strong> ' + code + escHtml(data.message) +
            '</div>';
        }
      })
      .catch(function (err) {
        resultTitle.innerHTML = '<i class="fas fa-times-circle text-danger mr-1"></i> Error';
        resultBody.innerHTML =
          '<div class="alert alert-danger mb-0">Request gagal: ' + escHtml(err.message) + '</div>';
      })
      .finally(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug mr-1"></i> Check WP Koneksi';
      });
  });

  function escHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }
});
</script>
