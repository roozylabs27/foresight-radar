# Integrasi LLM (Claude)

Foresight Radar memakai Claude untuk dua hal: mengusulkan sinyal dari dokumen yang ditempel analis (Fase 3a), dan menulis narasi empat kuadran skenario 2×2 (Fase 3d). Prinsipnya: **AI mengusulkan, analis memutuskan.** Semua hasil AI masuk sebagai PENDING (sinyal) atau DRAFT (skenario) dan diberi label asal.

Tanpa API key, kedua fitur tetap berjalan tanpa AI. Ekstraksi memakai aturan kata kunci, dan skenario memakai kerangka template berlabel "Kerangka tanpa AI".

## Konfigurasi
Semua lewat `.env`, dan dibaca di `config/services.php`.

| Kunci | Default | Arti |
|---|---|---|
| `ANTHROPIC_API_KEY` | kosong | Kosong berarti AI mati (`NullLlmClient`). |
| `ANTHROPIC_MODEL` | `claude-opus-5-5` | Model untuk semua panggilan. |
| `ANTHROPIC_EFFORT` | `medium` | Effort untuk ekstraksi sinyal. |
| `ANTHROPIC_NARRATIVE_EFFORT` | `medium` | Effort untuk narasi skenario. |
| `ANTHROPIC_TIMEOUT` | `120` | Detik per percobaan. Diterapkan di transport Guzzle, karena SDK menyerahkan timeout ke transport. |
| `ANTHROPIC_MAX_RETRIES` | `1` | Retry otomatis SDK. Total waktu maks sekitar 240 detik, di bawah batas 300 detik PHP dan nginx. |
| `ANTHROPIC_FALLBACKS` | `default` | Fallback server-side bila model menolak karena alasan keamanan (beta `server-side-fallback-2026-07-01`). API memilih model pengganti dan model yang benar-benar menjawab dicatat. Kosongkan untuk mematikan. |
| `SIGNAL_EXTRACTION_DRIVER` | `claude` | `heuristic` mematikan AI untuk ekstraksi walau key terisi. |
| `SIGNAL_EXTRACTION_MAX_CHARS` | `60000` | Dokumen yang lebih panjang ditolak (422) dengan pesan untuk memecahnya. Tidak ada pemotongan diam-diam. |
| `SIGNAL_EXTRACTION_MAX_CANDIDATES` | `8` | Batas sinyal per dokumen. |

Test memaksa key kosong dan driver `heuristic` lewat `phpunit.xml`. Semua test memakai `Tests\Support\FakeLlmClient`, tanpa jaringan.

SDK: `anthropic-ai/sdk` `~0.59.0` (versi minor di-pin). Kode ada di `app/Services/Llm/`:
- `StructuredLlmClient`: interface yang dipakai fitur.
- `AnthropicStructuredClient`: Messages API dengan structured output `json_schema`, system prompt yang di-cache, serta pemeriksaan `stop_reason` (`refusal`, `max_tokens`) sebelum konten dibaca.
- `NullLlmClient`: dipakai bila key kosong.
- `LlmException`: alasan kegagalan (`unavailable`, `rate_limited`, `overloaded`, `api_error`, `timeout`, `refusal`, `max_tokens`, `invalid_json`) dan pesan berbahasa Indonesia untuk pengguna.

## Data yang keluar dari server
Hanya bila key terisi:

| Fitur | Yang dikirim ke Anthropic |
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

Panggilan LLM untuk ekstraksi dilakukan sebelum transaksi DB dibuka, jadi panggilan yang lambat tidak menahan lock.

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
