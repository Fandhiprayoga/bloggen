# Product Requirements Document (PRD)

## Product Name
YouTube to SEO Blog Generator (WordPress Draft Publisher)

## Document Info
- Version: 1.0
- Date: 2026-05-05
- Status: Draft for implementation
- Owner: Product + Engineering

## 1. Executive Summary
Aplikasi ini mengubah satu link YouTube menjadi artikel blog SEO-friendly berbahasa Indonesia (tone santai edukatif), menyiapkan featured image dari stock image API, lalu mengirim hasilnya ke WordPress sebagai draft.

MVP dirancang untuk 1 website WordPress, dengan alur review dan edit manual sebelum submit ke WordPress. Tujuan utamanya adalah mempercepat produksi konten blog berkualitas SEO dari video tanpa menghilangkan kontrol editorial.

## 2. Problem Statement
Pembuatan artikel blog dari konten video saat ini memakan waktu tinggi karena proses manual:
- Menonton dan merangkum video
- Menulis artikel SEO
- Menyiapkan featured image
- Posting ke CMS (WordPress)

Dampaknya adalah throughput konten rendah dan konsistensi SEO tidak stabil.

## 3. Goals and Success Metrics
### 3.1 Business Goals
- Mempercepat produksi artikel dari sumber video
- Menstandarkan kualitas SEO dasar
- Mengurangi pekerjaan manual posting ke WordPress

### 3.2 Product Goals (MVP)
- User dapat submit 1 URL YouTube dan mendapatkan draft artikel siap review
- User dapat menambahkan featured image yang relevan
- User dapat mengirim hasil ke WordPress sebagai draft dengan category/tag

### 3.3 Success Metrics (MVP)
- Draft creation success rate ke WordPress > 95%
- Seluruh job menyimpan status akhir yang jelas (success/failed)
- Skor SEO internal melewati threshold yang ditentukan tim konten (nilai threshold ditetapkan sebelum QA)
- Waktu proses rata-rata tercatat pada sistem (monitoring, belum menjadi hard SLA)

## 4. Target Users and Persona
### Primary Persona
- Individual creator
- Tujuan: mengubah video YouTube menjadi artikel blog lebih cepat
- Kebutuhan: hasil tetap bisa diedit sebelum publish

## 5. Scope
### 5.1 In Scope (MVP)
- Input 1 URL YouTube
- Ambil transcript (mandatory)
- Gunakan data tambahan: judul, deskripsi, komentar
- Generate artikel 1200-1800 kata
- Generate elemen SEO:
  - SEO title
  - Meta description
  - SEO slug
  - Primary keyword + secondary keywords
  - Struktur heading H1-H3
  - FAQ schema block
- Ambil featured image dari stock image API
- Preview + edit manual hasil konten
- Submit ke 1 WordPress site sebagai draft
- Set category dan tag pada saat create post
- API credentials disimpan via environment variables server

### 5.2 Out of Scope (MVP)
- Multi-site WordPress
- Auto publish default (langsung publish)
- Kuota harian usage
- Fallback audio-to-text jika transcript tidak tersedia
- Auto update post WordPress existing

## 6. End-to-End User Flow
1. User membuka halaman Generate Content
2. User memasukkan URL YouTube
3. Sistem validasi URL
4. Sistem mengambil transcript video
5. Jika transcript tidak tersedia, job gagal dengan alasan jelas
6. Sistem mengambil metadata tambahan (judul, deskripsi, komentar)
7. Sistem memanggil LLM untuk generate artikel + SEO metadata
8. Sistem mengambil kandidat image dari stock image API
9. Sistem menampilkan hasil pada halaman editor + preview
10. User melakukan edit manual jika diperlukan
11. User memilih category/tag target WordPress
12. User klik Submit to WordPress
13. Sistem upload image ke WordPress Media Library
14. Sistem create post sebagai draft dan set featured image
15. Sistem menyimpan wordpress_post_id + status sinkronisasi

## 7. Functional Requirements
### 7.1 Module: YouTube Input and Validation
- FR-1: Sistem menerima URL YouTube valid dari user
- FR-2: Sistem menolak URL invalid dengan error message yang jelas
- FR-3: Sistem normalisasi URL (short/full format) sebelum diproses

### 7.2 Module: Transcript and Source Extraction
- FR-4: Sistem wajib mengambil transcript video untuk melanjutkan proses
- FR-5: Jika transcript tidak tersedia, sistem menghentikan proses dan memberi status failed
- FR-6: Sistem mengambil data tambahan: title, description, comments (jika tersedia)

### 7.3 Module: Content Generation
- FR-7: Sistem menggunakan OpenAI untuk menghasilkan artikel
- FR-8: Panjang artikel default 1200-1800 kata
- FR-9: Gaya bahasa default: Bahasa Indonesia, santai edukatif
- FR-10: Output artikel harus memiliki struktur heading H1-H3
- FR-11: Sistem menghasilkan SEO title, meta description, slug, keywords
- FR-12: Sistem menghasilkan FAQ section yang dapat dipakai untuk schema

### 7.4 Module: SEO Validation
- FR-13: Sistem menampilkan checklist SEO minimum pada halaman preview
- FR-14: Sistem memberi peringatan bila elemen SEO wajib belum lengkap

### 7.5 Module: Featured Image
- FR-15: Sistem mencari featured image melalui stock image API
- FR-16: User dapat memilih satu image dari kandidat yang tersedia
- FR-17: Sistem menyimpan metadata image (source URL, credit/license jika diperlukan)

### 7.6 Module: Review and Manual Editing
- FR-18: User dapat mengedit title, content, SEO fields sebelum submit
- FR-19: User dapat preview hasil final sebelum submit WordPress

### 7.7 Module: WordPress Publishing
- FR-20: Sistem membuat post baru di WordPress sebagai draft
- FR-21: Sistem upload featured image ke WordPress dan set sebagai featured media
- FR-22: Sistem menyetel category dan tag saat create post
- FR-23: Sistem menyimpan wordpress_post_id, response payload ringkas, status

### 7.8 Module: History and Audit
- FR-24: Sistem menyimpan riwayat job per URL
- FR-25: Sistem menampilkan status job: processing, ready, failed, submitted
- FR-26: Sistem menyimpan pesan error terstruktur untuk troubleshooting

## 8. Non-Functional Requirements
### 8.1 Security
- NFR-1: API keys tidak disimpan di database, hanya via env server
- NFR-2: API keys tidak boleh tampil di UI/log/error response
- NFR-3: Input URL dan payload API disanitasi

### 8.2 Reliability
- NFR-4: Retry terbatas untuk kegagalan sementara API eksternal
- NFR-5: Submit ke WordPress harus idempotent untuk mencegah duplicate post

### 8.3 Observability
- NFR-6: Setiap job punya correlation id unik
- NFR-7: Sistem menyimpan event log per tahap proses

### 8.4 Performance
- NFR-8: Sistem mencatat durasi per tahap (extract, generate, image, submit)
- NFR-9: Belum ada hard SLA pada MVP, tetapi baseline metric wajib tersedia

## 9. Roles and Permissions
MVP mengikuti pola role/permission existing.

Tambahan permission yang direkomendasikan:
- content.generate
- content.review
- content.submit_wp
- content.view_history
- content.manage_settings

## 10. Data Model (Draft)
### 10.1 Entity: content_jobs
- id (uuid)
- user_id
- youtube_url
- youtube_video_id
- source_title
- source_description
- source_comments_json
- transcript_text
- status (queued, processing, ready, failed, submitted)
- error_code
- error_message
- created_at
- updated_at

### 10.2 Entity: generated_articles
- id (uuid)
- content_job_id (fk)
- article_title
- article_body_html
- article_body_markdown
- word_count
- language
- tone
- primary_keyword
- secondary_keywords_json
- seo_title
- seo_meta_description
- seo_slug
- faq_json
- seo_score
- is_edited_manually (boolean)
- created_at
- updated_at

### 10.3 Entity: generated_images
- id (uuid)
- content_job_id (fk)
- provider_name
- source_url
- local_path
- credit_text
- license_info
- width
- height
- selected (boolean)
- created_at

### 10.4 Entity: wordpress_submissions
- id (uuid)
- content_job_id (fk)
- wp_site_key
- wp_post_id
- wp_post_url
- wp_media_id
- wp_status (draft/failed)
- wp_category_ids_json
- wp_tag_ids_json
- request_snapshot_json
- response_snapshot_json
- submitted_by
- submitted_at

## 11. External Integration Contracts
### 11.1 OpenAI (Text Generation)
- Purpose: generate article + SEO metadata
- Input:
  - transcript
  - title/description/comments
  - language, tone, word target
- Output:
  - title
  - body
  - SEO metadata
  - FAQ block

### 11.2 Transcript Provider
- Purpose: retrieve transcript from YouTube video
- Rule: transcript wajib tersedia
- Failure behavior: return failed with clear reason, no fallback in MVP

### 11.3 Stock Image API
- Purpose: find featured image candidates
- Output:
  - image URL
  - dimensions
  - attribution/license fields

### 11.4 WordPress REST API
- Endpoints used:
  - POST /wp-json/wp/v2/media
  - POST /wp-json/wp/v2/posts
- Required fields:
  - title
  - content
  - status=draft
  - featured_media
  - categories
  - tags

## 12. UX Requirements
### 12.1 Screens
- Generate Form Screen
  - Input URL YouTube
  - Action button: Generate
- Result Editor Screen
  - Editable title/content/SEO fields
  - SEO checklist panel
  - Featured image selector
  - Preview pane
  - Action button: Submit to WordPress
- History Screen
  - Daftar job dan status
  - Link ke hasil/detail error

### 12.2 UX Rules
- Error harus selalu actionable (contoh: transcript tidak tersedia)
- Tombol submit nonaktif jika field wajib belum terpenuhi
- Tampilkan status progres per tahap agar user tahu proses sedang berjalan

## 13. Acceptance Criteria
### 13.1 Generate Flow
- AC-1: URL valid dapat diproses hingga hasil editor muncul
- AC-2: URL invalid ditolak dengan pesan validasi
- AC-3: Tanpa transcript, proses berhenti dan status failed

### 13.2 SEO Output
- AC-4: Output memiliki SEO title, meta description, slug
- AC-5: Output memiliki H1-H3 dan FAQ block
- AC-6: Panjang artikel berada pada rentang 1200-1800 kata

### 13.3 WordPress Submission
- AC-7: User bisa submit hasil edit ke WordPress sebagai draft
- AC-8: Featured image berhasil terpasang pada draft WordPress
- AC-9: Category/tag terkirim sesuai pilihan user
- AC-10: Keberhasilan submit draft > 95% pada uji acceptance

### 13.4 Auditability
- AC-11: Sistem menyimpan history setiap job
- AC-12: Sistem menyimpan error detail untuk job gagal

## 14. Testing Strategy
### 14.1 Unit Tests
- URL validation and normalization
- SEO field generator/formatter
- Word count validator
- Slug sanitizer

### 14.2 Integration Tests
- OpenAI response contract parsing
- Stock image API response mapping
- WordPress media upload and post creation

### 14.3 End-to-End/UAT
- Skenario sukses: URL valid -> generate -> edit -> submit draft
- Skenario gagal: transcript tidak tersedia
- Skenario gagal: WordPress API error auth/timeout

## 15. Risks and Mitigation
- Risk: Transcript unavailable
  - Mitigation: fail-fast dengan messaging jelas + tracking error
- Risk: Inconsistent LLM output format
  - Mitigation: strict output schema + validation layer
- Risk: Stock image license ambiguity
  - Mitigation: simpan license metadata, batasi provider terpercaya
- Risk: WordPress API incompatibility
  - Mitigation: gunakan sandbox site untuk contract test

## 16. Milestones (MVP)
1. M1 - Foundation
- Data model + migration
- Service abstraction (YouTube/LLM/Image/WP)
- Basic UI form and history

2. M2 - Content Pipeline
- Transcript extraction
- OpenAI generation
- SEO metadata and validation
- Editor + preview

3. M3 - WordPress Submission
- Media upload
- Draft post creation
- Category/tag mapping
- Logging and retry

4. M4 - Hardening and UAT
- Acceptance test run
- Error handling improvements
- Documentation and handover

## 17. Open Decisions (Must be finalized before sprint execution)
- Provider transcript final yang akan dipakai
- Nilai threshold SEO score internal
- Daftar category/tag mapping default WordPress
- Standar lisensi image provider yang diizinkan

## 18. Implementation Notes for Current Codebase
- Gunakan pola route group + permission existing
- Tambahkan permission baru di AuthGroups
- Tambahkan controller/module baru untuk:
  - Content generation
  - Content history
  - WordPress submission
- Gunakan existing HTTP client untuk external API calls
- Simpan kredensial via env only sesuai keputusan MVP
