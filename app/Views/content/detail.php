<?php
/** @var array<string, mixed> $job */
/** @var array<string, mixed>|null $article */
/** @var list<array<string, mixed>> $images */
/** @var list<array<string, mixed>> $submissions */
/** @var list<array<string, mixed>> $categories */
/** @var list<array<string, mixed>> $tags */
?>

<div class="row">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">
        <h4>Informasi Job</h4>
      </div>
      <div class="card-body">
        <table class="table table-sm">
          <tr>
            <th style="width: 140px;">Job ID</th>
            <td><?= esc((string) ($job['id'] ?? '')) ?></td>
          </tr>
          <tr>
            <th>Status</th>
            <td><?= esc((string) ($job['status'] ?? '')) ?></td>
          </tr>
          <tr>
            <th>Source Type</th>
            <td><?= esc((string) ($job['source_type'] ?? 'prompt')) ?></td>
          </tr>
          <tr>
            <th>Prompt</th>
            <td class="text-break"><?= esc((string) ($job['prompt_text'] ?? '-')) ?></td>
          </tr>
          <tr>
            <th>Error Code</th>
            <td><?= esc((string) ($job['error_code'] ?? '-')) ?></td>
          </tr>
          <tr>
            <th>Error Message</th>
            <td><?= esc((string) ($job['error_message'] ?? '-')) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h4>WordPress Taxonomy</h4>
      </div>
      <div class="card-body">
        <?php if (activeGroupCan('content.submit_wp')): ?>
        <form action="<?= base_url('admin/content/sync-taxonomies/' . ($job['id'] ?? '')) ?>" method="post" class="mb-3">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline-primary btn-block">
            <i class="fas fa-sync-alt mr-1"></i> Sync Kategori & Tag Dari WordPress
          </button>
        </form>

        <div class="row">
          <div class="col-md-6">
            <form action="<?= base_url('admin/content/create-category/' . ($job['id'] ?? '')) ?>" method="post" class="mb-3">
              <?= csrf_field() ?>
              <div class="form-group mb-2">
                <label class="small mb-1">Tambah Kategori</label>
                <input type="text" name="name" class="form-control" placeholder="Nama kategori" required>
              </div>
              <div class="form-group mb-2">
                <input type="text" name="slug" class="form-control" placeholder="Slug (opsional)">
              </div>
              <button type="submit" class="btn btn-sm btn-info btn-block">Tambah Kategori ke WordPress</button>
            </form>
          </div>
          <div class="col-md-6">
            <form action="<?= base_url('admin/content/create-tag/' . ($job['id'] ?? '')) ?>" method="post" class="mb-3">
              <?= csrf_field() ?>
              <div class="form-group mb-2">
                <label class="small mb-1">Tambah Tag</label>
                <input type="text" name="name" class="form-control" placeholder="Nama tag" required>
              </div>
              <div class="form-group mb-2">
                <input type="text" name="slug" class="form-control" placeholder="Slug (opsional)">
              </div>
              <button type="submit" class="btn btn-sm btn-info btn-block">Tambah Tag ke WordPress</button>
            </form>
          </div>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">Anda tidak memiliki permission untuk sync taxonomy WordPress.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h4>Publish to WordPress</h4>
      </div>
      <div class="card-body">
        <?php if (! empty($article)): ?>
          <?php if (activeGroupCan('content.submit_wp')): ?>
          <form action="<?= base_url('admin/content/publish/' . ($job['id'] ?? '')) ?>" method="post">
            <?= csrf_field() ?>
            <div class="form-group">
              <label for="wp_category_ids">Pilih Kategori</label>
              <select class="form-control" id="wp_category_ids" name="wp_category_ids[]" multiple size="6">
                <?php foreach ($categories as $category): ?>
                  <option value="<?= esc((string) ($category['wp_term_id'] ?? '')) ?>">
                    <?= esc((string) ($category['name'] ?? '')) ?> (ID: <?= esc((string) ($category['wp_term_id'] ?? '')) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="wp_tag_ids">Pilih Tag</label>
              <select class="form-control" id="wp_tag_ids" name="wp_tag_ids[]" multiple size="6">
                <?php foreach ($tags as $tag): ?>
                  <option value="<?= esc((string) ($tag['wp_term_id'] ?? '')) ?>">
                    <?= esc((string) ($tag['name'] ?? '')) ?> (ID: <?= esc((string) ($tag['wp_term_id'] ?? '')) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="post_status">Status Publish</label>
              <select class="form-control" id="post_status" name="post_status">
                <option value="draft" selected>Draft &mdash; simpan sebagai draft</option>
                <option value="publish">Publish &mdash; langsung publik</option>
              </select>
            </div>
            <button type="submit" class="btn btn-success btn-block">
              <i class="fas fa-cloud-upload-alt mr-1"></i> Kirim ke WordPress
            </button>
          </form>
          <?php else: ?>
          <p class="text-muted mb-0">Anda tidak memiliki permission untuk publish ke WordPress.</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted mb-0">Artikel belum tersedia, jadi belum bisa dipublish ke WordPress.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h4>Riwayat Publish</h4>
      </div>
      <div class="card-body">
        <?php if (! empty($submissions)): ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($submissions as $submission): ?>
              <li class="mb-2 pb-2 border-bottom">
                <div>
                  <span class="badge badge-primary"><?= esc((string) ($submission['wp_status'] ?? 'draft')) ?></span>
                  <strong>Post ID:</strong> <?= esc((string) ($submission['wp_post_id'] ?? '-')) ?>
                </div>
                <div class="small text-muted">
                  <?= esc((string) ($submission['submitted_at'] ?? '-')) ?>
                </div>
                <?php if (! empty($submission['wp_post_url'])): ?>
                <a href="<?= esc((string) $submission['wp_post_url']) ?>" target="_blank" rel="noopener">Lihat draft di WordPress</a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted mb-0">Belum ada publish ke WordPress untuk job ini.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Featured Image</h4>
      </div>
      <div class="card-body">
        <?php
          $selectedImage = null;
          foreach ($images as $img) {
              if (! empty($img['selected'])) { $selectedImage = $img; break; }
          }
        ?>
        <?php if ($selectedImage): ?>
          <div class="mb-3 text-center">
            <img src="<?= esc((string) ($selectedImage['source_url'] ?? '')) ?>" alt="Featured Image"
              class="img-fluid rounded" style="max-height:200px;object-fit:cover;">
            <div class="small text-muted mt-1">
              Provider: <?= esc((string) ($selectedImage['provider_name'] ?? '-')) ?>
            </div>
          </div>
        <?php else: ?>
          <p class="text-muted mb-3">Belum ada featured image.</p>
        <?php endif; ?>

        <?php if (activeGroupCan('content.generate')): ?>
        <hr>
        <!-- AI Generate -->
        <form action="<?= base_url('admin/content/generate-image/' . ($job['id'] ?? '')) ?>" method="post" class="mb-3">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline-primary btn-block">
            <i class="fas fa-robot mr-1"></i> Generate Featured Image dengan AI
          </button>
          <small class="form-text text-muted">Akan menggantikan featured image yang ada.</small>
        </form>

        <!-- Manual Upload -->
        <form action="<?= base_url('admin/content/upload-image/' . ($job['id'] ?? '')) ?>" method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="form-group mb-2">
            <label class="small mb-1">Upload Gambar Manual</label>
            <input type="file" name="featured_image" class="form-control-file" accept="image/jpeg,image/png,image/webp,image/gif" required>
            <small class="form-text text-muted">Format: JPG, PNG, WEBP, GIF. Maks 5MB.</small>
          </div>
          <button type="submit" class="btn btn-outline-secondary btn-block">
            <i class="fas fa-upload mr-1"></i> Upload Gambar
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Hasil Artikel</h4>
        <?php if (activeGroupCan('content.generate') && ! empty($job['prompt_text'])): ?>
        <form action="<?= base_url('admin/content/regenerate-text/' . ($job['id'] ?? '')) ?>" method="post" onsubmit="return confirm('Regenerate akan menimpa hasil artikel saat ini. Lanjutkan?');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-warning btn-sm">
            <i class="fas fa-redo mr-1"></i> Regenerate Artikel
          </button>
        </form>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (! empty($article)): ?>
          <h5><?= esc((string) ($article['article_title'] ?? '')) ?></h5>
          <p class="text-muted mb-2">
            SEO Title: <?= esc((string) ($article['seo_title'] ?? '-')) ?>
          </p>
          <p class="text-muted mb-3">
            Meta Description: <?= esc((string) ($article['seo_meta_description'] ?? '-')) ?>
          </p>
          <hr>
          <?php if (! empty($article['article_body_html'])): ?>
            <div class="content-body"><?= $article['article_body_html'] ?></div>
          <?php else: ?>
            <pre class="bg-light p-3"><?= esc((string) ($article['article_body_markdown'] ?? '-')) ?></pre>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted mb-0">Belum ada artikel. Periksa status job dan konfigurasi API pada env jika proses gagal.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
