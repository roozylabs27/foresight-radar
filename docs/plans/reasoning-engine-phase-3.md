# Rencana: Fase 3 reasoning engine (Hipotesis & GenAI)

## Context
Fase 1 dan 2 dari `docs/plans/reasoning-engine-framework.md` sudah selesai, tetapi belum di-commit. Engine indeks sudah terjelaskan, EDA lengkap, rantai data sudah diperbaiki, dan ada riwayat rating, tren, serta matriks cross-impact −3..+3 dengan Vester.

Fase 3 membawa app ke tahap "hipotesis dan GenAI" dari kerangka PDF. Isinya:
- AI mengusulkan sinyal dengan bukti terverifikasi;
- loop kausal antar force terlihat;
- analis bisa menguji "bagaimana jika";
- skenario 2×2 disusun dari bukti nyata.

Keputusan user:
- keempat bagian masuk cakupan;
- penyedia LLM Claude lewat SDK PHP resmi `anthropic-ai/sdk`;
- ekstraksi berjalan sinkron saat ingest, tanpa antrian dulu.

Prinsip yang tidak boleh dilanggar:
- AI mengusulkan, analis memutuskan.
- Setiap kutipan bukti harus ada verbatim di sumber.
  - "Sumber" adalah dokumen yang ditempel analis di form Ingest (judul + teks lengkap): artikel berita, laporan riset, rilis regulasi, kutipan jurnal, atau notulen. Teks ini tersimpan di `sources.raw_content` dengan hash SHA-256.
  - URL hanya disimpan sebagai metadata. Fase 3 tidak mengambil isi halaman dari URL, karena fetch URL butuh perlindungan SSRF dan masuk fase berikutnya.
  - Kutipan dari Claude dicocokkan ke `raw_content` itu (spasi dinormalisasi). Kutipan yang tidak ditemukan dibuang dan tidak pernah tersimpan.
- Migrasi hanya menambah, tidak mengubah yang lama.
- Kerja langsung di root project, tanpa commit dan tanpa trailer Co-Authored-By.

Status: rencana disetujui 16 Oktober 2026 dan diimplementasikan di hari yang sama. Belum di-commit. Bagian dari roadmap di `docs/plans/reasoning-engine-framework.md`.

| Bagian | Status |
|---|---|
| 3b. Causal loops | Selesai. |
| 3c. Simulasi what-if | Selesai. |
| S. Plumbing LLM | Selesai. SDK `anthropic-ai/sdk` `~0.59.0`. `llm:smoke` dan uji dengan key nyata belum dijalankan (menunggu persetujuan biaya). |
| 3a. Ekstraksi sinyal LLM | Selesai, diverifikasi tanpa key dan dengan `FakeLlmClient`. |
| 3d. Skenario 2×2 + narasi | Selesai, diverifikasi tanpa key (kerangka template) dan dengan `FakeLlmClient`. |

Penyimpangan dari rencana:
- **Fallback server-side langsung dipasang.** Parameter `fallbacks: "default"` (beta `server-side-fallback-2026-07-01`) sudah ada di binding PHP, jadi dipakai sejak awal, bukan sebagai tindak lanjut. Bisa dimatikan lewat `ANTHROPIC_FALLBACKS=`.
- **Timeout diterapkan di Guzzle.** Opsi `timeout` di `RequestOptions` SDK hanya bersifat anjuran ("enforced by the caller-supplied transport"), sehingga 120 detik dipasang di transport Guzzle. `maxRetries` memakai opsi SDK.
- **Kerangka template tidak butuh bukti.** Aturan 422 untuk sumbu tanpa sinyal ACCEPTED hanya berlaku bila LLM akan dipanggil. Tanpa key, kerangka tetap bisa dibuat walau belum ada bukti, karena database lokal belum punya sinyal sama sekali.
- **`is_ai_generated` tetap `true` untuk sinyal heuristik**, supaya kompatibel dengan data dan test lama. Asal sinyal yang sebenarnya ada di kolom baru `extraction_provider` (`claude` atau `heuristic`), dan UI memakai kolom itu.
- **Instalasi SDK lewat filesystem container.** `composer require` gagal di mount 9p karena file baru dikunci antivirus/indexer Windows. SDK dipasang di `/tmp` container, lalu disalin ke `vendor/` dan `installed.json` didaftarkan. Hasilnya identik dengan dist (3431 file).

## Urutan
**3b → 3c → S (plumbing LLM) → 3a → 3d.**

3b dan 3c tidak memakai LLM, jadi risikonya paling kecil dan bisa langsung dipakai. S tidak berbuat apa-apa selama API key belum diisi. 3d memerlukan S dan sinyal ACCEPTED yang sudah ditautkan ke force.

## 3b. Deteksi causal loop (tanpa LLM)
- **Baru: `app/Services/CausalLoopService.php`**, method `detect(array $nodeIds, array $edges)` dengan input array murni.
  - Algoritma: DFS terbatas dari tiap node, hanya maju ke node dengan indeks lebih besar dari titik awal. Dengan begitu tiap siklus elementer ditemukan sekali, tanpa duplikat rotasi.
  - Batas: `MAX_LENGTH=5`, `MAX_LOOPS=300`, `MAX_STEPS=200000`. Jika batas tercapai, `truncated` bernilai true.
  - Hanya edge dengan skor ≠ 0 yang dihitung.
  - Polaritas: jumlah edge negatif genap berarti R (Penguat), ganjil berarti B (Penyeimbang).
  - Kekuatan: `(Π|s|)^(1/k)/3`, bernilai 0..1.
  - Urutan hasil: kekuatan menurun, lalu panjang menaik.
- **Ubah `app/Services/CrossImpactService.php`:** `matrix()` menambahkan kunci `loops` dari data yang sudah dimuat, tanpa query atau endpoint baru. `update()` sudah mengembalikan `matrix()`, jadi daftar loop ikut segar setiap kali skor diubah.
- **Ubah `resources/js/Pages/Report/CrossImpact.jsx`:** `LoopPanel` dengan filter Semua/R/B. Tiap baris menampilkan badge R atau B, jalur "A → B → C → A" dengan chip skor, dan bar kekuatan. Klik baris menyorot sel loop di matriks dan meredupkan sel lain. Ada keadaan kosong dan catatan bila hasil terpotong. Sorotan di radar ditunda.
- **Docs:** bagian "Causal loops" di `docs/product/REASONING_ENGINE.md`.

## 3c. Simulasi what-if (hanya baca)
- **Keputusan v1:** efek langsung saja, tanpa propagasi cross-impact, karena skor −3..+3 belum punya pemetaan terkalibrasi ke impact/uncertainty. UI menyatakannya dengan jelas.
- **Ubah `app/Services/ReasoningEngineService.php`:** ekstrak `loadRatings($dateRange, $dimension)` dari `getExecutiveOverview` (query plus `with`). Tidak ada perubahan rumus atau konstanta.
- **Baru: `app/Services/ScenarioSimulationService.php`,** method `simulate($dateRange, $dimension, $overrides)`.
  - Rating di-`clone`, lalu skor dan `setRelation('status_action', …)` diubah lewat `StatusActionType`. Tidak pernah disimpan.
  - Hasil dihitung ulang dengan `explainResilienceIndex`, `explainGrowthIndex`, dan `determineReadinessStatus`.
  - Mengembalikan baseline dan simulasi (indeks, postur, penjelasan), selisih, `posture_changed`, dan selisih per force yang di-override.
  - Force di luar cakupan filter menghasilkan 422.
- **Baru: `app/Http/Requests/SimulationRequest.php`.**
  - `overrides` maks 50.
  - `force` berupa uuid yang ada di tabel driving force.
  - `status_action` bernilai `monitoring` atau `decided_plan`.
  - `impact` dan `uncertainty` bilangan 1..10.
- **Route:** `POST /dashboard/simulate` di `DashboardController::simulate`, dengan izin `view-dashboard` dan throttle 60 per menit.
- **UI:**
  - Ekspor `Gauge`, `GaugePanel`, `Factor`, dan `FactorList` dari `ExecutiveOverview.jsx`.
  - Komponen baru `resources/js/Components/ScenarioSimulation.jsx`. Daftar force diambil dari kontribusi di `reasoning-data`. Tiap force punya toggle Monitoring/Decided Plan, select impact dan uncertainty, dan tombol reset. Request POST di-debounce 400 ms.
  - Hasilnya: gauge dengan chip selisih, postur sebelum → sesudah, dan daftar selisih yang memakai `Factor`.
  - Banner: "Simulasi tidak menyimpan perubahan. Efek lintas-force belum dihitung."
  - Tab baru "Simulasi What-if" di `Dashboard.jsx`.

## S. Plumbing LLM bersama
- **Pasang SDK:** `docker exec -e COMPOSER_HOME=/tmp/composer laravel_app composer require anthropic-ai/sdk`. Jalankan `--dry-run` dulu. Setelah terpasang, cek nama opsi timeout/maxRetries dan kelas exception koneksi di `vendor/`, jangan ditebak. Pin minor version-nya.
- **Konfigurasi `config/services.php`:**
  - `anthropic`: `api_key`, `model` (default `claude-opus-5-5`), `effort` dan `narrative_effort` (default `medium`), `timeout` 120 detik, `max_retries` 1. Totalnya maks 240 detik, masih di bawah batas 300 detik PHP/nginx.
  - `signal_extraction`: `driver` (`claude` atau `heuristic`), `max_chars` 60000, `max_candidates` 8.
  - `.env.example` mendapat kunci baru dengan nilai kosong.
  - `phpunit.xml` memaksa key kosong dan driver `heuristic`.
- **Baru: `app/Services/Llm/`:**
  - Interface `StructuredLlmClient` dengan method `isAvailable`, `provider`, `model`, dan `generateJson(LlmRequest): LlmResponse`.
  - Objek `LlmRequest` dan `LlmResponse`.
  - `LlmException` dengan alasan `unavailable|rate_limited|overloaded|api_error|timeout|refusal|max_tokens|invalid_json` dan `userMessage()` berbahasa Indonesia.
  - `AnthropicStructuredClient`:
    - Memanggil `messages->create` dengan system prompt yang di-cache (`cacheControl` ephemeral), user message, dan `outputConfig` berisi `effort` + `format` json_schema.
    - Memeriksa `stopReason` (`refusal`, `max_tokens`) sebelum membaca konten.
    - Men-decode JSON dari blok teks pertama.
    - `APIStatusException` dipetakan lewat `->type`.
    - Log hanya berisi tujuan, model, stop reason, usage, dan durasi. Tidak pernah berisi teks dokumen atau key.
  - `NullLlmClient`.
  - Binding di `AppServiceProvider`: client Anthropic dipakai bila key terisi, `NullLlmClient` bila tidak.
- **Baru: `app/Support/EvidenceVerifier.php`.** Spasi dinormalisasi, pencocokan case-sensitive, kutipan minimal 20 karakter.
- **Baru: `php artisan llm:smoke`.** Hanya dijalankan dengan persetujuan user karena memakan biaya.
- **Aturan prompt injection:**
  - Teks sumber hanya masuk ke user message, di dalam tag `<source_document>` atau `<evidence>`, dan tag penutup literal di dalamnya di-escape.
  - System prompt menyatakan isi tag adalah data, bukan instruksi.
  - Model hanya memakai referensi `F1..Fn`/`S1..Sn` yang dipetakan di server, tidak pernah ID database.

## 3a. Ekstraksi sinyal dengan LLM
- **Migrasi `add_extraction_provenance`:**
  - `signals` mendapat `extraction_provider`, `extraction_model`, `prompt_version`, `quote_verified` (nullable; null berarti data lama), dan `suggested_driving_force_id`.
  - `sources` mendapat `extraction_meta` (json).
- **Baru: `app/Services/Llm/Prompts/SignalExtractionPrompt.php`** (`extract-v1`).
  - System prompt bersifat konstan:
    - kutipan disalin persis, 1–3 kalimat, maks 400 karakter, tanpa elipsis atau terjemahan;
    - judul diawali frasa benda ≤4 kata, mengikuti `WordCountRule` saat accept;
    - skor memakai rubrik 1–10;
    - confidence terkalibrasi beserta alasannya;
    - bahasa mengikuti dokumen.
  - Schema: dimensi dan horizon berupa enum nama dari DB, ditambah `related_force_ref`.
- **Baru: `app/Services/Extraction/LlmSignalExtractor.php`.**
  - Nama dimensi dan horizon dipetakan ke ID.
  - Nilai di-clamp: skor 1..10, confidence 0..0,95.
  - Kandidat dengan kutipan yang tidak terverifikasi dibuang dan dihitung.
  - Kutipan duplikat dihapus, lalu kandidat dibatasi `max_candidates`.
- **Ubah `app/Services/SignalExtractionService.php`:**
  - Panggilan LLM dipindah ke luar `DB::transaction`.
  - Method heuristik yang protected tetap ada, supaya `RatingDataChainTest` yang men-subclass service tetap jalan.
  - Bila terjadi `LlmException`, ekstraksi fallback ke heuristik dengan pemberitahuan.
  - Bila LLM menghasilkan 0 kandidat terverifikasi, sumber tetap disimpan tanpa sinyal, tanpa fallback.
  - Teks alasan heuristik dibuat jujur: "berbasis kata kunci (tanpa AI)".
- **Ubah `IngestSourceRequest`:** `content` dibatasi `max_chars` dengan pesan untuk memecah dokumen. Tidak ada pemotongan diam-diam.
- **Ubah `SignalIngestionController`:**
  - `extractionMode` dikirim ke halaman.
  - Pesan hasil ingest mencantumkan model, jumlah sinyal, dan jumlah yang dibuang.
  - Review hanya boleh saat PENDING; selain itu 409.
  - Kutipan yang disunting diverifikasi ulang.
  - Accept ditolak (422) bila kutipan kosong atau `quote_verified === false`.
- **UI:**
  - `Signals/Index.jsx`: badge "Claude" atau "Aturan", status verifikasi kutipan, baris "Diekstrak oleh", dan usulan force.
  - `IngestForm.jsx`: catatan provenance sesuai mode, plus penghitung karakter.
  - `ReviewForm.jsx`: blok bukti read-only dan `driving_force_id` yang terisi dari usulan.
  - Pemberitahuan saat fallback memakai `toast.warning`.

## 3d. Matriks skenario 2×2 dan narasi
- **Migrasi `create_scenarios_tables`:**
  - Izin `view-scenario` dan `manage-scenario` dibuat dengan pola migrasi `2026_10_16_000002`, dan juga ditambahkan ke `permission.json` dan `role.json`.
  - Tabel `scenarios`:
    - nama dan pertanyaan fokus;
    - force sumbu A dan B;
    - empat label kutub;
    - status DRAFT/FINAL;
    - provenance;
    - `input_signal_ids`.
  - Tabel `scenario_quadrants`:
    - posisi HH/HL/LH/LL;
    - judul, narasi, implikasi, dan `early_indicators`;
    - `cited_signal_ids`, `is_ai_generated`, `provider`;
    - kolom edited.
- **Baru: `ScenarioService`.**
  - `suggestAxes()`: delapan force APPROVED teratas menurut impact × uncertainty. Pasangan yang diusulkan harus berbeda dimensi dan tidak saling memengaruhi kuat (|cross-impact| < 2).
  - `evidenceFor()`: sinyal ACCEPTED yang tertaut ke kedua force sumbu, maks 30 per sumbu.
- **Baru: `ScenarioNarrativePrompt`** (`scenario-v1`, bahasa Indonesia, satu panggilan untuk empat kuadran) **dan `ScenarioNarrativeService`.**
  - Bila salah satu sumbu belum punya sinyal, respons 422 dan LLM tidak dipanggil.
  - Bila ada kuadran yang sudah disunting, respons 409 kecuali `overwrite_edited`.
  - Referensi `[Sx]` disaring sehingga hanya referensi yang diberikan yang tersisa.
  - Bila ada kuadran tanpa sitasi valid, seluruh hasil ditolak dan tidak ada yang disimpan.
  - Fallback berupa kerangka template berlabel "Kerangka tanpa AI".
- **HTTP:** `ScenarioController` dan form request.
  - Route `/scenarios`: index, fetch, suggest-axes, dan show dengan izin view; create, update, quadrant, dan delete dengan izin manage.
  - `POST /{scenario}/generate` dengan throttle 10 per menit.
- **UI:**
  - Menu "Scenarios" di `AuthenticatedLayout`.
  - Halaman `resources/js/Pages/Report/Scenarios.jsx`:
    - daftar skenario dan detail berupa grid 2×2;
    - `FormDialog` untuk skenario baru, dengan sumbu terisi dari usulan dan peringatan saling memengaruhi.
  - Komponen `Components/Scenario/QuadrantCard.jsx`:
    - chip `[Sx]` yang membuka popover kutipan dan sumber;
    - badge Claude / Kerangka / Diedit analis;
    - form edit di dalam Sheet.
  - Tombol generate menampilkan status proses panjang, dengan konfirmasi sebelum menimpa hasil suntingan.

## Test (tanpa jaringan)
- **Infrastruktur:** `tests/Support/FakeLlmClient.php` di-bind lewat `TestCase::fakeLlm()`. Fake ini mencatat request dan level transaksi DB saat dipanggil.
- **Unit:**
  - `CausalLoopServiceTest`: polaritas, rotasi, arah terbalik, edge nol, batas panjang, truncation, kekuatan pasti.
  - `EvidenceVerifierTest`.
  - Uji decode Anthropic: refusal, max_tokens, JSON rusak, blok thinking sebelum teks.
- **Feature:**
  - `CrossImpactTest` (loops).
  - `ScenarioSimulationTest`: tanpa override sama dengan overview; selisih pasti; 422; 403; dan bukti tidak ada penulisan ke DB.
  - `LlmSignalExtractionTest`:
    - pemetaan nama ke ID;
    - kutipan palsu dibuang;
    - clamp nilai;
    - referensi usulan force;
    - provenance tersimpan;
    - fallback saat rate limit;
    - 422 untuk dokumen terlalu panjang;
    - escape tag penutup;
    - panggilan LLM di luar transaksi.
  - `SignalIngestionTest` (409, 422 untuk kutipan tidak terverifikasi, data lama tetap bisa diterima).
  - `ScenarioTest`: izin, suggest-axes, pemetaan sitasi, 422 tanpa sinyal atau tanpa sitasi, 409 untuk kuadran yang disunting, template saat tanpa key atau saat refusal.
- **Test lama yang harus tetap hijau:** `RatingDataChainTest`, `ReasoningEngineTest`, `RateLimitingTest` (15 per menit), `ContainerHardeningTest`, `SessionAndHeaderHardeningTest`.
- **E2E:**
  - `visualizations.e2e.ts`: panel loop dan sorotan sel.
  - `executive-reasoning.e2e.ts`: tab simulasi dan chip selisih.
  - Baru `scenarios.e2e.ts`: jalur template tanpa key.

## Verifikasi
1. Pasang SDK, lalu `php artisan migrate`, lalu `docker restart laravel_app` (opcache).
2. `docker exec laravel_app php artisan test` hijau, dan `npm run build` hijau.
3. `npx e2e run` untuk `visualizations`, `executive-reasoning`, dan `scenarios` terhadap http://127.0.0.1:8080.
4. Cek manual tanpa key: label "Aturan" tampil dan skenario menghasilkan kerangka template.
5. Dengan key nyata, **setelah user menyetujui biayanya:**
   - jalankan `llm:smoke`;
   - ingest satu artikel nyata (cek provenance, kutipan terverifikasi, dan lama proses);
   - pastikan log tidak berisi konten atau key;
   - generate satu skenario;
   - cek `cache_read_input_tokens`.
6. Docs diperbarui:
   - `REASONING_ENGINE.md` (loop, simulasi);
   - Status di `reasoning-engine-framework.md`;
   - `DOMAIN_GAPS.md` Q9;
   - `FEATURE_INVENTORY.md`;
   - dokumen baru `docs/product/LLM_INTEGRATION.md` (prompt dan versinya, aturan grounding, data yang keluar dari server, konfigurasi, biaya).

## Risiko dan mitigasi
| Risiko | Dampak | Mitigasi di Fase 3 |
|---|---|---|
| **Latensi.** Satu panggilan Opus bisa 30–120 detik. | Analis menunggu lama. Satu worker PHP-FPM (maks 20) tertahan selama panggilan. | (1) Timeout 120 detik dan retry maks 1, total ≤240 detik, di bawah batas 300 detik. (2) LLM dipanggil di luar transaksi DB, jadi tidak ada lock lama. (3) UI menampilkan status "sedang diproses" dan mencegah klik ganda. (4) Throttle ingest 15/menit dan generate skenario 10/menit. (5) Plumbing terpisah di `app/Services/Llm`, sehingga pindah ke antrian nanti tanpa mengubah fitur. |
| **Biaya API.** Sekitar $0,15 per ingest terburuk dan $0,10–0,20 per skenario (Opus 5.5: $4/$20 per juta token). | Tagihan naik bila dipakai berulang. | (1) Input dibatasi 60.000 karakter; lebih dari itu ditolak dengan pesan untuk memecah dokumen. (2) Satu panggilan untuk empat kuadran. (3) Throttle per menit. (4) System prompt di-cache. (5) Usage token dicatat di log per panggilan. (6) Bisa dimatikan total lewat key kosong atau driver `heuristic`. (7) Model dan effort bisa diubah lewat env tanpa ubah kode. (8) `llm:smoke` dan uji dengan key nyata hanya dijalankan atas persetujuan user. |
| **Halusinasi kutipan atau sitasi.** | Bukti palsu masuk ke basis data dan tampak kredibel. | (1) Setiap kutipan diverifikasi verbatim ke `raw_content`; yang tidak cocok dibuang. (2) Accept ditolak bila kutipan kosong atau tidak terverifikasi. (3) Narasi skenario hanya boleh mengutip referensi `S1..Sn` yang dikirim server; referensi lain dihapus. (4) Bila ada kuadran tanpa sitasi valid, seluruh hasil ditolak dan tidak ada yang disimpan. (5) Status verifikasi tampil di UI. |
| **Prompt injection.** Teks artikel berisi instruksi tersembunyi seperti "abaikan aturan di atas". | Model bisa dibelokkan. | (1) Teks sumber hanya masuk ke user message di dalam tag `<source_document>`, dengan tag penutup di dalamnya di-escape. (2) System prompt menyatakan isi tag adalah data, bukan instruksi. (3) Output dipaksa JSON schema, dan nama dimensi/horizon dibatasi enum dari DB. (4) ID database tidak pernah dibuat model; server yang memetakan referensi. (5) Semua hasil AI masuk sebagai PENDING atau DRAFT dan harus diputuskan analis. |
| **Refusal.** Classifier keamanan bisa menolak artikel tertentu, misalnya bioteknologi. | Ekstraksi gagal untuk dokumen sah. | (1) `stop_reason` diperiksa sebelum membaca konten. (2) Refusal memicu fallback ke heuristik untuk ekstraksi, atau ke kerangka template untuk skenario. (3) Pemberitahuan ditampilkan ke analis lewat toast. (4) Tindak lanjut: parameter `fallbacks` server-side setelah binding PHP-nya dicek. |
| **API tidak tersedia** (rate limit, overload, gangguan). | Fitur berhenti. | (1) Retry otomatis SDK satu kali. (2) Fallback ke heuristik atau template dengan pesan yang jelas. (3) Error dipetakan ke pesan bahasa Indonesia, dan detail teknis tidak bocor ke pengguna (`HandlesApiErrors`). |
| **Data keluar ke Anthropic.** Teks sumber dikirim ke API saat key diisi. | Masalah kerahasiaan untuk dokumen internal. | (1) Didokumentasikan di `docs/product/LLM_INTEGRATION.md`: data apa yang dikirim. (2) Bisa dimatikan lewat `SIGNAL_EXTRACTION_DRIVER=heuristic` atau key kosong. (3) Log tidak pernah berisi teks dokumen, prompt, atau API key. (4) Mode yang aktif (Claude atau Aturan) ditampilkan di form Ingest. |
| **Perubahan SDK.** | Nama opsi atau kelas di SDK berbeda dari dugaan. | (1) Nama timeout, retry, dan exception dicek langsung di `vendor/` sebelum dipakai. (2) Versi minor SDK di-pin. (3) Parsing respons diuji dengan objek stub. |
