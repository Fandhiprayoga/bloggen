<?php
/** @var list<array<string, mixed>> $latestJobs */
?>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h4>Generate Dari Prompt</h4>
      </div>
      <div class="card-body">
        <form action="<?= base_url('admin/content/generate') ?>" method="post">
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="prompt_text">Prompt Konten</label>
            <textarea
              class="form-control"
              id="prompt_text"
              name="prompt_text"
              rows="7"
              placeholder="Contoh: Buat artikel SEO tentang strategi email marketing untuk bisnis UMKM, sertakan tips praktis, kesalahan umum, dan FAQ."
              required
            ><?= old('prompt_text') ?></textarea>
            <small class="form-text text-muted">
              Tulis brief konten sejelas mungkin agar hasil artikel dan feature image AI lebih relevan.
            </small>
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label for="article_style">Gaya Artikel</label>
              <select class="form-control" id="article_style" name="article_style" required>
                <?php $selectedStyle = old('article_style', 'informative'); ?>
                <option value="informative" <?= $selectedStyle === 'informative' ? 'selected' : '' ?>>Informatif</option>
                <option value="tutorial" <?= $selectedStyle === 'tutorial' ? 'selected' : '' ?>>Tutorial Step-by-step</option>
                <option value="listicle" <?= $selectedStyle === 'listicle' ? 'selected' : '' ?>>Listicle</option>
                <option value="storytelling" <?= $selectedStyle === 'storytelling' ? 'selected' : '' ?>>Storytelling</option>
              </select>
            </div>

            <div class="form-group col-md-4">
              <label for="word_target">Panjang Artikel</label>
              <select class="form-control" id="word_target" name="word_target" required>
                <?php $selectedWordTarget = old('word_target', '1200-1800'); ?>
                <option value="800-1200" <?= $selectedWordTarget === '800-1200' ? 'selected' : '' ?>>800-1200 kata</option>
                <option value="1200-1800" <?= $selectedWordTarget === '1200-1800' ? 'selected' : '' ?>>1200-1800 kata</option>
                <option value="1800-2500" <?= $selectedWordTarget === '1800-2500' ? 'selected' : '' ?>>1800-2500 kata</option>
              </select>
            </div>

            <div class="form-group col-md-4">
              <label for="tone">Tone Penulisan</label>
              <select class="form-control" id="tone" name="tone" required>
                <?php $selectedTone = old('tone', 'santai-edukatif'); ?>
                <option value="santai-edukatif" <?= $selectedTone === 'santai-edukatif' ? 'selected' : '' ?>>Santai Edukatif</option>
                <option value="profesional-formal" <?= $selectedTone === 'profesional-formal' ? 'selected' : '' ?>>Profesional Formal</option>
                <option value="persuasive-marketing" <?= $selectedTone === 'persuasive-marketing' ? 'selected' : '' ?>>Persuasive Marketing</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="ai_provider">Provider AI</label>
            <select class="form-control" id="ai_provider" name="ai_provider">
              <?php $selectedProvider = old('ai_provider', 'openai'); ?>
              <option value="openai" <?= $selectedProvider === 'openai' ? 'selected' : '' ?>>OpenAI (gpt-4o-mini &mdash; cloud)</option>
              <option value="ollama" <?= $selectedProvider === 'ollama' ? 'selected' : '' ?>>Ollama (lokal &mdash; pastikan server berjalan)</option>
            </select>
            <small class="form-text text-muted">Ollama membutuhkan server Ollama berjalan di lokal (default: http://localhost:11434).</small>
          </div>

          <div id="ollama-check-section" style="display:none;" class="mb-3">
            <button type="button" id="btn-check-ollama" class="btn btn-sm btn-outline-info">
              <i class="fas fa-plug mr-1"></i> Cek Koneksi Ollama
            </button>
            <span id="ollama-check-result" class="ml-2 small"></span>
          </div>

          <div id="ollama-model-section" style="display:none;">
            <div class="form-group">
              <label for="ollama_model">Model Ollama</label>
              <select class="form-control" id="ollama_model" name="ollama_model" disabled>
                <option value="<?= esc($ollamaDefaultModel ?? 'llama3') ?>">
                  <?= esc($ollamaDefaultModel ?? 'llama3') ?> (dari konfigurasi)
                </option>
              </select>
              <small class="form-text text-muted">Klik "Cek Koneksi Ollama" untuk memuat daftar model yang tersedia.</small>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fas fa-magic mr-1"></i> Generate Konten
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h4>Catatan MVP</h4>
      </div>
      <div class="card-body">
        <ul class="pl-3 mb-0">
          <li>Bahasa output: Indonesia (santai edukatif)</li>
          <li>Panjang target: 1200-1800 kata</li>
          <li>Output WordPress: draft</li>
          <li>Provider AI: OpenAI</li>
          <li>Featured image: AI generation</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h4>10 Job Terbaru</h4>
        <div class="card-header-action">
          <a href="<?= base_url('admin/content/history') ?>" class="btn btn-outline-primary btn-sm">Lihat Semua</a>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>Job ID</th>
                <th>Prompt Ringkas</th>
                <th>Status</th>
                <th>Dibuat</th>
              </tr>
            </thead>
            <tbody>
              <?php if (! empty($latestJobs)): ?>
                <?php foreach ($latestJobs as $job): ?>
                  <tr>
                    <td>
                      <a href="<?= base_url('admin/content/detail/' . $job['id']) ?>">
                        <?= esc(substr((string) ($job['id'] ?? ''), 0, 8)) ?>...
                      </a>
                    </td>
                    <td class="text-truncate" style="max-width: 280px;"><?= esc((string) ($job['prompt_text'] ?? '-')) ?></td>
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
                    <td><?= esc((string) ($job['created_at'] ?? '-')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" class="text-center">Belum ada job konten.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const providerSelect = document.getElementById('ai_provider');
  const checkSection = document.getElementById('ollama-check-section');
  const btnCheck = document.getElementById('btn-check-ollama');
  const resultSpan = document.getElementById('ollama-check-result');
  const checkUrl = '<?= base_url('admin/content/check-ollama') ?>';

  function toggleSection() {
    const isOllama = providerSelect.value === 'ollama';
    checkSection.style.display = isOllama ? 'block' : 'none';
    document.getElementById('ollama-model-section').style.display = isOllama ? 'block' : 'none';
    document.getElementById('ollama_model').disabled = !isOllama;
    if (!isOllama) resultSpan.textContent = '';
  }

  providerSelect.addEventListener('change', toggleSection);
  toggleSection(); // run on page load

  btnCheck.addEventListener('click', function () {
    btnCheck.disabled = true;
    resultSpan.className = 'ml-2 small text-muted';
    resultSpan.textContent = 'Memeriksa...';

    fetch(checkUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.json(); })
      .then(function (data) {
                if (data.ok && Array.isArray(data.available_models) && data.available_models.length > 0) {
                  const sel = document.getElementById('ollama_model');
                  const current = sel.value;
                  sel.innerHTML = '';
                  data.available_models.forEach(function (m) {
                    const opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = m;
                    if (m === current || m === data.configured_model) opt.selected = true;
                    sel.appendChild(opt);
                  });
                }
        if (data.ok) {
          resultSpan.className = data.model_found
            ? 'ml-2 small text-success'
            : 'ml-2 small text-warning';
        } else {
          resultSpan.className = 'ml-2 small text-danger';
        }
        resultSpan.textContent = data.message || '-';
      })
      .catch(function (err) {
        resultSpan.className = 'ml-2 small text-danger';
        resultSpan.textContent = 'Gagal fetch: ' + err.message;
      })
      .finally(function () {
        btnCheck.disabled = false;
      });
  });
})();
</script>
