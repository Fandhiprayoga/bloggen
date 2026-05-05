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
        <form id="generate-content-form" action="<?= base_url('admin/content/generate') ?>" method="post">
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

          <button type="submit" id="btn-generate-content" class="btn btn-primary">
            <i class="fas fa-magic mr-1"></i> Generate Konten
          </button>
          <button type="button" id="btn-stop-generate" class="btn btn-outline-danger ml-2" style="display:none;">
            <i class="fas fa-stop-circle mr-1"></i> Stop
          </button>
        </form>

        <div id="generate-progress-panel" class="alert alert-light border mt-3 mb-0" style="display:none;">
          <div class="d-flex align-items-center mb-2">
            <div class="spinner-border spinner-border-sm text-primary mr-2" role="status" aria-hidden="true"></div>
            <strong id="generate-progress-title">Memproses generate konten...</strong>
          </div>
          <ul id="generate-progress-log" class="mb-0 pl-3"></ul>
        </div>

        <div id="generate-live-panel" class="card border mt-3" style="display:none;">
          <div class="card-header py-2">
            <h6 class="mb-0">Streaming Konten (Realtime)</h6>
          </div>
          <div class="card-body py-2">
            <div id="generate-live-content" class="mb-0" style="max-height:320px; overflow:auto;"></div>
          </div>
        </div>
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
  const streamUrl = '<?= base_url('admin/content/generate-stream') ?>';
  const generateForm = document.getElementById('generate-content-form');
  const generateButton = document.getElementById('btn-generate-content');
  const stopButton = document.getElementById('btn-stop-generate');
  const progressPanel = document.getElementById('generate-progress-panel');
  const progressTitle = document.getElementById('generate-progress-title');
  const progressLog = document.getElementById('generate-progress-log');
  const livePanel = document.getElementById('generate-live-panel');
  const liveContent = document.getElementById('generate-live-content');
  let activeAbortController = null;
  let liveBuffer = '';

  function appendProgress(message, cssClass) {
    const li = document.createElement('li');
      li.textContent = message;
    if (cssClass) {
      li.className = cssClass;
    }
    progressLog.appendChild(li);
  }

  function setGeneratingState(isGenerating) {
    generateButton.disabled = isGenerating;
    stopButton.style.display = isGenerating ? 'inline-block' : 'none';
    stopButton.disabled = !isGenerating;
    if (isGenerating) {
      generateButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Generating...';
      progressPanel.style.display = 'block';
      progressLog.innerHTML = '';
      progressTitle.textContent = 'Memproses generate konten...';
      livePanel.style.display = 'none';
      liveContent.innerHTML = '';
      liveBuffer = '';
    } else {
      generateButton.innerHTML = '<i class="fas fa-magic mr-1"></i> Generate Konten';
      activeAbortController = null;
    }
  }

  function escapeHtml(input) {
    return input
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function markdownToHtml(markdown) {
    let html = escapeHtml(markdown || '');

    html = html.replace(/^###\s+(.*)$/gm, '<h6>$1</h6>');
    html = html.replace(/^##\s+(.*)$/gm, '<h5>$1</h5>');
    html = html.replace(/^#\s+(.*)$/gm, '<h4>$1</h4>');
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    html = html.replace(/^\-\s+(.*)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
    html = html.replace(/\n\n+/g, '</p><p>');
    html = html.replace(/\n/g, '<br>');

    return '<p>' + html + '</p>';
  }

  function extractBodyMarkdown(rawJsonLikeText) {
    const match = rawJsonLikeText.match(/"body_markdown"\s*:\s*"((?:\\.|[^"\\])*)"/s);
    if (!match || !match[1]) {
      return null;
    }

    try {
      const wrapped = '{"value":"' + match[1].replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"}';
      return JSON.parse(wrapped).value;
    } catch (error) {
      return null;
    }
  }

  function appendLiveChunk(text) {
    if (!text) {
      return;
    }

    liveBuffer += text;
    livePanel.style.display = 'block';

    const extractedMarkdown = extractBodyMarkdown(liveBuffer);
    if (extractedMarkdown) {
      liveContent.innerHTML = markdownToHtml(extractedMarkdown);
    } else {
      liveContent.innerHTML = '<p class="text-muted mb-0">Menyusun preview markdown...</p>';
    }

    liveContent.scrollTop = liveContent.scrollHeight;
  }

  function parseSseChunk(chunk) {
    const blocks = chunk.split('\n\n');
    return blocks
      .map(function (block) {
        const lines = block.split('\n');
        let eventName = 'message';
        let data = '';
        lines.forEach(function (line) {
          if (line.startsWith('event:')) {
            eventName = line.slice(6).trim();
          }
          if (line.startsWith('data:')) {
            data += line.slice(5).trim();
          }
        });
        return { event: eventName, data: data };
      })
      .filter(function (x) { return x.data !== ''; });
  }

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

  generateForm.addEventListener('submit', function (event) {
    event.preventDefault();
    setGeneratingState(true);
    appendProgress('Mengirim permintaan generate ke server...', 'text-muted');
    activeAbortController = new window.AbortController();

    fetch(streamUrl, {
      method: 'POST',
      headers: {
        Accept: 'text/event-stream',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: new window.FormData(generateForm),
      signal: activeAbortController.signal
    })
      .then(function (response) {
        if (!response.ok || !response.body) {
          throw new Error('HTTP ' + response.status + ' ' + response.statusText);
        }

        const reader = response.body.getReader();
          const decoder = new window.TextDecoder('utf-8');
        let buffer = '';

        function pump() {
          return reader.read().then(function (result) {
            if (result.done) {
              return;
            }

            buffer += decoder.decode(result.value, { stream: true });
            const segments = buffer.split('\n\n');
            buffer = segments.pop() || '';

            segments.forEach(function (segment) {
              parseSseChunk(segment + '\n\n').forEach(function (payload) {
                let data = {};
                try {
                  data = JSON.parse(payload.data);
                } catch (e) {
                  return;
                }

                if (payload.event === 'progress') {
                  appendProgress(data.message || 'Progress update...', 'text-muted');
                } else if (payload.event === 'content_chunk') {
                  appendLiveChunk(data.chunk || '');
                } else if (payload.event === 'error') {
                  progressTitle.textContent = 'Generate gagal';
                  appendProgress(data.message || 'Terjadi error.', 'text-danger');
                  setGeneratingState(false);
                } else if (payload.event === 'complete') {
                  progressTitle.textContent = 'Generate selesai';
                  appendProgress(data.message || 'Selesai.', 'text-success');
                  if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                  } else {
                    setGeneratingState(false);
                  }
                } else if (payload.event === 'done') {
                  if (!data.success) {
                    setGeneratingState(false);
                  }
                }
              });
            });

            return pump();
          });
        }

        return pump();
      })
      .catch(function (error) {
        if (error && error.name === 'AbortError') {
          progressTitle.textContent = 'Generate dibatalkan';
          appendProgress('Proses generate dibatalkan oleh user.', 'text-warning');
          return;
        }

        progressTitle.textContent = 'Generate gagal';
        appendProgress('Gagal membaca stream: ' + error.message, 'text-danger');
        setGeneratingState(false);
      })
      .finally(function () {
        setGeneratingState(false);
      });
  });

  stopButton.addEventListener('click', function () {
    if (!activeAbortController) {
      return;
    }

    stopButton.disabled = true;
    appendProgress('Memutus stream generate...', 'text-warning');
    activeAbortController.abort();
  });
})();
</script>
