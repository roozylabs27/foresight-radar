# Integrasi LLM

Foresight Radar memakai LLM untuk dua hal: mengusulkan sinyal dari dokumen yang ditempel analis (Fase 3a), dan menulis narasi empat kuadran skenario 2×2 (Fase 3d). Prinsipnya: **AI mengusulkan, analis memutuskan.** Semua hasil AI masuk sebagai PENDING (sinyal) atau DRAFT (skenario) dan diberi label asal.

Tanpa provider yang terkonfigurasi, kedua fitur tetap berjalan tanpa AI. Ekstraksi memakai aturan kata kunci, dan skenario memakai kerangka template berlabel "Kerangka tanpa AI".

## Memilih provider
`LLM_PROVIDER` memilih adapter:

| Nilai | Adapter | Cocok untuk |
|---|---|---|
| `anthropic` (default) | `AnthropicStructuredClient`, lewat SDK resmi | Claude lewat API Anthropic. Kredit API dibeli di Console; langganan Claude Pro/Max tidak berlaku. |
| `openai_compatible` | `OpenAiCompatibleClient`, lewat HTTP client Laravel | Semua API `/chat/completions`, seperti yang dipakai opencode: OpenRouter, OpenCode Zen, Ollama atau LM Studio lokal, Groq, DeepSeek, Gemini (endpoint OpenAI), dan lainnya. |

Provider hanya aktif bila konfigurasinya lengkap. Kalau tidak, app memakai `NullLlmClient` dan berjalan tanpa AI. Nama provider (misalnya `claude` atau `openrouter`) tersimpan di setiap sinyal (`extraction_provider`) dan skenario (`provider`), dan labelnya tampil di UI.

### Contoh `.env` untuk provider OpenAI-compatible
| Provider | `OPENAI_COMPATIBLE_BASE_URL` | Lainnya |
|---|---|---|
| OpenRouter | `https://openrouter.ai/api/v1` | `OPENAI_COMPATIBLE_API_KEY` dari openrouter.ai, `OPENAI_COMPATIBLE_MODEL` berupa id model OpenRouter. |
| OpenCode Zen | `https://opencode.ai/inference/openai/v1` | Key dari opencode.ai/console, `NAME=opencode`, `LABEL="OpenCode Zen"`. Hanya model berformat Chat Completions, misalnya `deepseek-v4-pro`, `glm-5.3`, `kimi-k3`, `qwen3.8-max`, `minimax-m3`, `mistral-large-4`. Model GPT, Claude, dan Gemini di Zen memakai endpoint lain dan tidak bisa lewat adapter ini. |
| Google Gemini | `https://generativelanguage.googleapis.com/v1beta/openai` | Key dari Google AI Studio, `NAME=gemini`, `LABEL=Gemini`, model misalnya `gemini-3.8-flash`. Dukungan OpenAI di Gemini masih beta. Bila `json_schema` ditolak (400), pakai `json_object`. Di tier gratis, Google dapat memakai data untuk perbaikan produk, jadi jangan dipakai untuk dokumen internal. |
| Ollama lokal | `http://host.docker.internal:11434/v1` | `ALLOW_NO_KEY=true`, `NAME=ollama`, model misalnya `qwen2.5:14b`. Teks tidak keluar dari jaringan Anda. |
| LM Studio lokal | `http://host.docker.internal:1234/v1` | `ALLOW_NO_KEY=true`, `NAME=lm-studio`. |

Semua kunci di tabel itu berawalan `OPENAI_COMPATIBLE_`.

Catatan:
- **Structured output.** `OPENAI_COMPATIBLE_STRUCTURED_OUTPUT=json_schema` (default) mengirim schema sebagai `response_format` yang strict. Bila provider atau model tidak mendukungnya (sering terjadi pada model lokal kecil), pakai `json_object`. Schema lalu ditulis di akhir user message, dan JSON yang dibungkus ```json tetap terbaca. Aturan grounding di bawah tetap berlaku: kutipan yang tidak verbatim dibuang, apa pun modelnya.
- **Kualitas.** Ekstraksi dan narasi diuji dengan `FakeLlmClient` dan HTTP palsu. Kualitas nyata tergantung model yang dipilih. Model kecil cenderung lebih sering menghasilkan kutipan yang tidak verbatim, yang lalu dibuang.
- **Batasan.** Fallback server-side dan prompt caching eksplisit hanya ada di adapter Anthropic. Provider OpenAI-compatible mencatat `cached_tokens` bila provider melaporkannya.

| Kunci (`openai_compatible`) | Default | Arti |
|---|---|---|
| `OPENAI_COMPATIBLE_NAME` / `_LABEL` | `openrouter` / `OpenRouter` | Id yang disimpan dan nama yang tampil. |
| `OPENAI_COMPATIBLE_BASE_URL` | `https://openrouter.ai/api/v1` | Basis URL; app memanggil `{base}/chat/completions`. |
| `OPENAI_COMPATIBLE_API_KEY` | kosong | Dikirim sebagai `Authorization: Bearer`. |
| `OPENAI_COMPATIBLE_MODEL` | kosong | Wajib diisi, misalnya `openai/gpt-4o-mini` di OpenRouter. |
| `OPENAI_COMPATIBLE_STRUCTURED_OUTPUT` | `json_schema` | Atau `json_object`. |
| `OPENAI_COMPATIBLE_ALLOW_NO_KEY` | `false` | `true` untuk server lokal tanpa key. |
| `OPENAI_COMPATIBLE_REASONING_EFFORT` | kosong | Bila diisi (`low`, `medium`, `high`), dikirim sebagai `reasoning_effort`. Kosong berarti tidak dikirim, karena sebagian provider menolak parameter yang tidak dikenal. |
| `OPENAI_COMPATIBLE_TIMEOUT` / `_MAX_RETRIES` | `120` / `1` | Retry untuk 429, 5xx, dan error koneksi. Jaga `timeout × (retry + 1)` di bawah 300 detik. |

**Hasil uji Gemini (16 Oktober 2026, `gemini-3.8-flash`).** `llm:smoke` berhasil dengan `json_schema` strict (11 detik). Ekstraksi artikel 9.000 karakter dengan `reasoning_effort=low` menghasilkan JSON valid, tetapi butuh sekitar 147 detik, sehingga melewati timeout 120 detik. Percobaan berikutnya ditolak Google dengan 503 "high demand"; fallback ke aturan berjalan sesuai rancangan. Untuk Gemini, pakai `OPENAI_COMPATIBLE_REASONING_EFFORT=low`, `OPENAI_COMPATIBLE_TIMEOUT=240`, dan `OPENAI_COMPATIBLE_MAX_RETRIES=0`, atau pilih model Flash yang lebih ringan bila sedang padat.

Dengan **`gemini-3.5-flash-lite`** (`reasoning_effort=low`), kedua jalur berhasil:
- **Ingest artikel 9.000 karakter:** 18 detik dan 2.537/493 token. Dua sinyal diusulkan, kedua kutipan terverifikasi verbatim, dan usulan force-nya relevan.
- **Narasi skenario empat kuadran:** 7 detik dan 607/1.029 token. Setiap kuadran mengutip `[S1]` dan `[S2]` serta berisi tiga indikator awal. Narasinya sekitar 80–95 kata, lebih pendek dari target 120–200 kata, sehingga model yang lebih besar memberi narasi yang lebih kaya.

## Konfigurasi Anthropic
Semua lewat `.env`, dan dibaca di `config/services.php`.

| Kunci | Default | Arti |
|---|---|---|
| `ANTHROPIC_API_KEY` | kosong | Kosong berarti adapter Anthropic tidak aktif. |
| `ANTHROPIC_MODEL` | `claude-opus-5-5` | Model untuk semua panggilan. |
| `ANTHROPIC_EFFORT` | `medium` | Effort untuk ekstraksi sinyal. |
| `ANTHROPIC_NARRATIVE_EFFORT` | `medium` | Effort untuk narasi skenario. |
| `ANTHROPIC_TIMEOUT` | `120` | Detik per percobaan. Diterapkan di transport Guzzle, karena SDK menyerahkan timeout ke transport. |
| `ANTHROPIC_MAX_RETRIES` | `1` | Retry otomatis SDK. Total waktu maks sekitar 240 detik, di bawah `LLM_JOB_TIMEOUT`. |
| `ANTHROPIC_FALLBACKS` | `default` | Fallback server-side bila model menolak karena alasan keamanan (beta `server-side-fallback-2026-07-01`). API memilih model pengganti dan model yang benar-benar menjawab dicatat. Kosongkan untuk mematikan. |
| `SIGNAL_EXTRACTION_DRIVER` | `llm` | `heuristic` mematikan AI untuk ekstraksi walau provider terkonfigurasi. Nilai lama `claude` tetap berarti `llm`. |
| `SIGNAL_EXTRACTION_MAX_CHARS` | `60000` | Dokumen yang lebih panjang ditolak (422) dengan pesan untuk memecahnya. Tidak ada pemotongan diam-diam. |
| `SIGNAL_EXTRACTION_MAX_CANDIDATES` | `8` | Batas sinyal per dokumen. |

Test memaksa key kosong dan driver `heuristic` lewat `phpunit.xml`. Semua test memakai `Tests\Support\FakeLlmClient`, tanpa jaringan.

SDK: `anthropic-ai/sdk` `~0.59.0` (versi minor di-pin). Kode ada di `app/Services/Llm/`:
- `StructuredLlmClient`: interface yang dipakai fitur.
- `AnthropicStructuredClient`: Messages API dengan structured output `json_schema`, system prompt yang di-cache, serta pemeriksaan `stop_reason` (`refusal`, `max_tokens`) sebelum konten dibaca.
- `NullLlmClient`: dipakai bila key kosong.
- `LlmException`: alasan kegagalan (`unavailable`, `rate_limited`, `overloaded`, `api_error`, `timeout`, `refusal`, `max_tokens`, `invalid_json`) dan pesan berbahasa Indonesia untuk pengguna.

## Data yang keluar dari server
Hanya bila provider terkonfigurasi. Teks dikirim ke provider yang dipilih di `LLM_PROVIDER`, kecuali server lokal (Ollama, LM Studio) yang tidak meninggalkan jaringan Anda:

| Fitur | Yang dikirim ke provider |
|---|---|
| Ekstraksi sinyal | Judul dan teks lengkap dokumen yang ditempel, nama dimensi dan horizon, serta keyword dan dimensi driving force APPROVED (maks 80) dengan label F1..Fn. |
| Narasi skenario | Nama skenario, pertanyaan fokus, keyword kedua sumbu dan label kutubnya, serta judul, kutipan bukti, dan judul sumber sinyal ACCEPTED yang tertaut (maks 30 per sumbu) dengan label S1..Sn. |

Yang tidak pernah dikirim: ID database, data pengguna, URL sumber, isi halaman dari URL (URL tidak di-fetch), dan skor rating.

Mode yang aktif tampil di form Ingest: badge "Claude · model" atau "Aturan", disertai keterangan apakah teks keluar dari server.

## Prompt dan versinya
Prompt adalah kelas PHP di `app/Services/Llm/Prompts/`. System prompt bersifat konstan supaya bisa di-cache. Bagian yang bervariasi masuk ke user message dan schema. Ganti `VERSION` setiap kali isi atau schema berubah makna. Versi itu tersimpan di setiap sinyal (`prompt_version`) dan skenario.

| Versi | Kelas | Isi |
|---|---|---|
| `extract-v1` | `SignalExtractionPrompt` | Sinyal dari satu dokumen: kutipan verbatim 1–3 kalimat (maks 400 karakter), judul berawalan frasa benda ≤4 kata, rubrik impact/uncertainty 1–10, confidence 0–0,95 beserta alasan, bahasa mengikuti dokumen, dan `related_force_ref`. |
| `scenario-v1` | `ScenarioNarrativePrompt` | Empat kuadran dalam satu panggilan, berbahasa Indonesia, masing-masing berisi judul, narasi 120–200 kata dengan sitasi `[Sx]`, implikasi, dan indikator awal. |

## Aturan grounding
- **Kutipan sinyal harus verbatim.** `App\Support\EvidenceVerifier` mencocokkan kutipan ke `sources.raw_content`. Spasi dinormalisasi, pencocokan case-sensitive, dan panjang minimal 20 karakter. Kutipan yang tidak ditemukan dibuang dan tidak pernah tersimpan. Jumlah yang dibuang dicatat di `sources.extraction_meta` dan disebut di pesan hasil ingest.
- **Kutipan yang disunting diverifikasi ulang.** Sinyal dengan `quote_verified = false` atau kutipan kosong tidak bisa diterima (422). Sinyal lama (`quote_verified = null`) tetap bisa diterima.
- **Review hanya saat PENDING.** Sinyal yang sudah diterima atau ditolak menghasilkan 409.
- **Model tidak pernah membuat ID.** Dimensi dan horizon dibatasi enum nama dari DB, dan server memetakannya ke ID. Usulan force dan sitasi memakai referensi F1..Fn dan S1..Sn yang dipetakan server.
- **Sitasi skenario disaring.** Referensi yang tidak diberikan dihapus dari narasi. Bila ada kuadran tanpa sitasi valid, seluruh hasil ditolak (422) dan tidak ada yang disimpan.
- **Narasi AI butuh bukti.** Bila salah satu sumbu belum punya sinyal ACCEPTED, respons 422 dan LLM tidak dipanggil. Kerangka template tanpa AI tetap bisa dibuat tanpa bukti.

## Prompt injection
- Teks sumber hanya masuk ke user message, di dalam tag `<source_document>`, `<evidence>`, dan sejenisnya. Tag penutup data di dalam teks di-escape (`PromptText::tag`), sehingga teks dokumen tidak bisa "keluar" dari blok datanya.
- System prompt menyatakan bahwa isi tag adalah data, bukan instruksi.
- Output dipaksa mengikuti JSON schema, dan enum membatasi nilai yang boleh dipilih.
- Semua hasil AI tetap harus diputuskan analis.

## Kegagalan dan fallback
| Kejadian | Ekstraksi sinyal | Narasi skenario |
|---|---|---|
| Key kosong / driver `heuristic` | Aturan kata kunci, label "Aturan" | Kerangka template |
| Rate limit, overload, timeout, error API | Fallback ke aturan dengan `toast.warning` | Kerangka template dengan peringatan |
| Refusal (setelah fallback server-side) | Fallback ke aturan | Kerangka template |
| `max_tokens` atau JSON rusak | Fallback ke aturan | Kerangka template |
| 0 kandidat terverifikasi | Sumber tetap disimpan tanpa sinyal, tanpa fallback | – |
| Salah satu kegagalan di atas saat membuat ulang narasi yang sudah ditulis AI | – | Tidak ada yang disimpan; narasi AI sebelumnya tetap, status `failed` dengan pesan "Narasi sebelumnya tidak diubah" |

Fallback ke kerangka template hanya terjadi bila skenario belum punya kuadran tulisan AI, supaya gangguan sesaat (misalnya 503 "high demand") tidak menimpa narasi yang sudah ada.

Panggilan LLM untuk ekstraksi dilakukan sebelum transaksi DB dibuka, jadi panggilan yang lambat tidak menahan lock.

## Antrian, latensi, dan risiko
Satu panggilan LLM bisa makan 10–150 detik. Uji nyata dengan Gemini: `gemini-3.5-flash-lite` selesai dalam 18 detik (ingest) dan 7 detik (skenario), sedangkan `gemini-3.8-flash` timeout di 120 detik lalu mendapat 503 "high demand". Karena itu panggilan LLM tidak lagi berjalan di request HTTP, tetapi di job antrian:

| Alur | Request | Job | Status yang di-poll UI |
|---|---|---|---|
| Ingest sinyal | `POST /signals/ingest` menyimpan `Source` dengan `extraction_status = queued`, lalu langsung menjawab 202 | `App\Jobs\ExtractSignals` memanggil `LlmSignalExtractor` (fallback aturan kata kunci tetap sama) dan menyimpan sinyal | `GET /signals/sources/{uuid}/extraction`: `status`, `message`, `extraction` (provider, model, jumlah sinyal, jumlah dibuang, `fallback`, `notice`), `error` |
| Narasi skenario | `POST /scenarios/{uuid}/generate` tetap mengecek 409 (kuadran disunting, atau generasi lain masih berjalan) dan 422 (sumbu tanpa bukti), lalu menjawab 202 | `App\Jobs\GenerateScenarioNarrative` memanggil LLM, memvalidasi sitasi, dan menyimpan kuadran | `GET /scenarios/{uuid}`: `generation.status`, `generation.message`, `generation.notice`, `generation.error` |

Status: `queued` → `processing` → `completed` atau `failed` (kolom `sources.extraction_status`/`extraction_error`, `scenarios.generation_status`/`generation_error`/`generation_notice`). Baris lama tanpa status dianggap selesai. UI mem-poll setiap 2,5 detik selama maksimal 6 menit; analis boleh meninggalkan halaman. Halaman Sinyal melanjutkan polling untuk ekstraksi milik analis yang masih berjalan (`pendingExtractions`), dan halaman Skenario melanjutkannya saat skenario dibuka.

Penolakan yang baru diketahui di job (sitasi tidak valid, kuadran disunting saat job menunggu, bukti hilang, atau LLM gagal saat narasi AI sudah ada) tidak lagi menjadi 422/409, tetapi disimpan di `generation_error` dan tampil sebagai toast dan peringatan di panel skenario. Tidak ada yang disimpan dalam kasus itu.

Worker: service `queue` di `docker-compose.yml` (`laravel_queue`) menjalankan `php artisan queue:work --tries=1` dengan driver `database` (`QUEUE_CONNECTION=database`, tabel `jobs`).

| Kunci | Default | Arti |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | `sync` menjalankan job di dalam request (seperti dulu, tanpa worker); UI tetap bekerja. Test memakai `sync`. |
| `LLM_JOB_TIMEOUT` | `300` | Detik maksimal satu job LLM. Harus lebih besar dari timeout × (1 + retry) provider. |
| `DB_QUEUE_RETRY_AFTER` | `360` | Detik sebelum job yang dianggap macet diberikan ke worker lain. Harus lebih besar dari `LLM_JOB_TIMEOUT`, kalau tidak panggilan LLM bisa dobel. |

Risiko dan penanganannya:
- **Worker mati atau belum dijalankan.** Job menumpuk dengan status `queued`; UI berhenti mem-poll setelah 6 menit dengan pesan bahwa proses masih di antrean. Cek `docker ps` untuk `laravel_queue`. Generasi skenario yang macet lebih lama dari `DB_QUEUE_RETRY_AFTER` boleh dimulai ulang.
- **Kode PHP berubah.** Worker memuat kode sekali saat start, jadi jalankan `docker restart laravel_queue` setelah mengubah PHP (selain `docker restart laravel_app` untuk opcache). Worker juga keluar sendiri setiap jam (`--max-time=3600`) dan dinyalakan ulang oleh `restart: unless-stopped`.
- **Job gagal karena error server atau timeout job.** Job tidak di-retry (`--tries=1`, `$tries = 1`) agar panggilan berbayar tidak terulang; status menjadi `failed` dengan pesan untuk analis, dan detailnya di log (`Signal extraction job failed`, `Scenario narrative job failed`) serta tabel `failed_jobs`. Sumber yang gagal tetap tersimpan tanpa sinyal; analis meng-ingest ulang.
- **Pengiriman ganda.** Job hanya berjalan bila statusnya masih `queued`, jadi pengiriman kedua tidak menggandakan sinyal atau panggilan LLM.
- **Satu worker, antrian berurutan.** Beberapa ingest sekaligus diproses satu per satu; jalankan lebih dari satu container worker bila antrean sering panjang.

## Log
Setiap panggilan mencatat `llm.call` dengan tujuan, model yang menjawab, stop reason, usage token (termasuk `cache_read_input_tokens`), dan durasi. Kegagalan dicatat sebagai `llm.error` dengan tujuan, model, alasan, status HTTP, dan durasi. Log tidak pernah berisi teks dokumen, prompt, atau API key.

## Biaya
Harga Claude Opus 5.5: $4 per juta token input dan $20 per juta token output, dengan cache read $0,20. Perkiraan kasar:
- **Ingest dokumen 60.000 karakter** (sekitar 15.000 token input, ditambah output dan thinking): sekitar $0,15 dalam kasus terburuk.
- **Satu generasi skenario:** sekitar $0,10–0,20.

Pengendali biaya: batas panjang dokumen, satu panggilan untuk empat kuadran, throttle (ingest 15/menit, generate skenario 10/menit), system prompt yang di-cache, effort dan model yang bisa diubah lewat env, serta sakelar mati lewat key kosong atau driver `heuristic`.

## Cek dengan key nyata
Memakan biaya, jadi hanya dijalankan atas persetujuan pemilik akun:
1. `php artisan llm:smoke` mengirim satu panggilan kecil (effort `low`), dan meminta konfirmasi kecuali diberi `--yes`.
2. Ingest satu artikel nyata. Periksa provenance, kutipan terverifikasi, dan lama proses.
3. Pastikan log tidak berisi konten atau key.
4. Generate satu skenario, lalu periksa `cache_read_input_tokens` di log pada panggilan kedua.

Status per 16 Oktober 2026: belum dijalankan. Semua verifikasi dilakukan tanpa key.
