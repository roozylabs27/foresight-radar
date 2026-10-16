# Analisis: kerangka AI Business Reasoning Engine untuk Foresight Radar

## Ringkasan
`docs/1765250420756.pdf` ("AI Business Reasoning Engine Foundation", Khusna Tsawabi Abdulloh) menawarkan kerangka berpikir analitik, bukan resep AI. Data mentah dibawa naik melalui tujuh tingkat reasoning, dari *Contextual* sampai *Epistemic*, dengan CRISP-DM sebagai pipeline datanya. Hasilnya berupa indeks yang bisa dijelaskan (Growth & Resilience Index) dan kerangka keputusan yang terus disempurnakan.

Foresight Radar sudah memakai sebagian kecil kerangka ini. Taksonomi dimensi memberi konteks, ada hitungan frekuensi dan tren, dan `ReasoningEngineService` menghasilkan Growth/Resilience Index. Namun tahap yang membuat reasoning bisa dipercaya belum ada: hipotesis kausal, validasi empiris, penjelasan "mengapa", dan sintesis. Engine indeks yang ada pun punya bug yang membuat dua dari empat postur tidak pernah muncul.

Rekomendasi: perbaiki fondasinya dulu tanpa ML (Fase 1). Isinya engine indeks yang benar dan terjelaskan, EDA lengkap, dan rantai data yang tidak membuang informasi. Pattern recognition (clustering, PCA, decision tree) baru layak setelah ada riwayat skor dan volume data yang cukup.

## Status
Per 16 Oktober 2026. Rumus, bobot, dan keputusan desain dicatat di `docs/product/REASONING_ENGINE.md`.

| Fase | Status | Isi |
|---|---|---|
| 1a. Engine indeks | Selesai | Growth dan Resilience berupa rata-rata berbobot 0–100 dengan baseline 50 dan bobot horizon. Ambang postur 60/60, dan keempat postur punya test bernilai pasti. |
| 1b. Causal reasoning terjelaskan | Selesai | Kontribusi per force dan aturan pemicu dikirim lewat `/dashboard/reasoning-data` dan ditampilkan di panel "Mengapa skor ini?". Status dicocokkan lewat enum `app/Enums`. |
| 1c. EDA lengkap | Selesai | Grafik frekuensi, distribusi skor, scatter dengan korelasi Pearson, dan sebaran impact per dimensi dan horizon. Tren mencakup minimal 12 bulan, dengan label "Dimensi Makro & Mezzo". |
| 1d. Rantai data | Selesai | Radar deterministik, deteksi Mid Term diperbaiki, `accept_create` membuat rating dari skor sinyal, dan perubahan skor dicatat di `driving_force_rating_histories`. |
| 2. Tren dan kecepatan keputusan | Selesai | Tren skor per dimensi dari riwayat rating, serta lama dari Monitoring ke Decided Plan. Keduanya punya keadaan kosong yang jujur selama riwayat masih pendek. |
| 2. Cross-impact (INT-013) | Selesai, versi terkecil | Matriks berarah −3..+3 yang diisi analis, ringkasan Vester (Pendorong, Kritis, Indikator, Penyangga), dan panah antar force di Foresight Radar. |
| 2. Clustering | Sengaja ditunda | Baru dibuat setelah ada ≥150 force APPROVED, ≥6 bulan riwayat, dan cluster yang stabil. Alasannya ada di dokumen produk. |
| 3–4 | Belum dimulai | – |

## Kerangka di PDF
| Tahap | Isi | Fase CRISP-DM |
|---|---|---|
| Brainstorming | GenAI menyusun hipotesis; Causal Loop Diagram menggambarkan dugaan feedback loop. Masih asumtif dan harus divalidasi. | Business Understanding |
| Data Analysis | Data cleaning, lalu EDA dengan empat jenis analisis: frekuensi, distribusi, hubungan (korelasi), perbandingan antar kelompok | Data Understanding, Preparation |
| Dashboard | Dashboard interaktif dengan filter sebagai jembatan dari deskriptif ke pola sistemik | Data Understanding |
| Pattern Recognition | K-Means (cluster Efficient/Growth/Risk), PCA 2D/3D, Correlation-Based Feature Selection, Decision Tree Regression untuk menemukan aturan | Modeling |
| Workflow Reasoning | Causal reasoning, lalu rule-based scoring, lalu Growth & Resilience Index. Iteratif sampai logika kausal konsisten. | Modeling, Evaluation |
| Reasoning Engine | ML pipeline plus business rules, input skenario, rekomendasi | Deployment |
| Prototype | Bukti konsep untuk menguji logika dan desain reasoning, bukan produk | Deployment |
| Framework Synthesis | Theory reconciliation: hasil dicocokkan dengan literatur dan dikunci menjadi prinsip, logika keputusan, dan roadmap | Evaluation |

Prinsip penutupnya adalah alur Data → Reasoning → Prototype → Refinement, dengan reasoning yang harus diperluas melampaui satu jenis metrik.

Keterbatasan: tabel *Reasoning Matrix* (hal. 3) dan diagram workflow, activity, dan sequence (hal. 12–14) berupa gambar dan tidak terbaca sebagai teks. Nama ketujuh tingkat reasoning di antara *Contextual* dan *Epistemic* tidak dirinci di dokumen ini.

## Pemetaan ke Foresight Radar
| Tahap PDF | Padanan di app | Status | Bukti |
|---|---|---|---|
| Konteks bisnis | Taksonomi 8 dimensi: Macro (Economy, Ecology, Regulation, Technology) dan Mezzo (Substitute, Supplier, Customer, Competitor) | Ada | `database/data/dimension.json` |
| Hipotesis | Driving force (keyword, deskripsi, PIC) dan sinyal dari sumber | Sebagian: tidak ada relasi kausal antar force | `app/Models/DrivingForce.php`, `app/Models/Signal.php` |
| Brainstorming GenAI | Ekstraksi sinyal dari teks | Belum: aturan kata kunci, bukan LLM | `app/Services/SignalExtractionService.php:186-210` |
| Data cleaning | Validasi form request dan `WordCountRule` | Sebagian | `app/Http/Requests/`, `app/Rules/WordCountRule.php` |
| EDA: frekuensi | Jumlah force per dimensi, dipisah Decided Plan dan Monitoring | Ada | `ReasoningEngineService.php:195-212` |
| EDA: distribusi | – | Belum | – |
| EDA: hubungan | Matriks impact × uncertainty (visual, tanpa korelasi) | Sebagian | `app/Http/Resources/PrioritizingResource.php` |
| EDA: perbandingan | – | Belum | – |
| Dashboard | Overview, Prioritizing, Radar, Registered List dengan filter periode dan dimensi | Ada | `resources/js/Pages/Dashboard.jsx` |
| Pattern recognition | – | Belum | – |
| Rule discovery | Aturan prioritas tetap (≥6 → High/Medium/Low) | Sebagian: aturan ditulis tangan, bukan ditemukan dari data | `app/Models/DrivingForceRating.php:64-82` |
| Causal reasoning | Arah dampak dari status aksi (Decided Plan menaikkan, Monitoring menurunkan) | Sebagian: implisit di rumus, tidak dijelaskan ke pengguna | `ReasoningEngineService.php:56-116` |
| Rule-based scoring dan indeks | Growth Index, Resilience Index, postur 2×2, Top 3 critical forces | Ada, dengan bug | `ReasoningEngineService.php:21-190` |
| Input skenario dan rekomendasi | Hanya empat kalimat postur tetap | Belum | `ReasoningEngineService.php:121-156` |
| Prototype | Overview dan e2e `executive-reasoning` | Ada | `resources/js/Components/ExecutiveOverview.jsx` |
| Framework synthesis | – | Belum | – |

Posisi app kira-kira ada di tingkat konteks sampai scoring. Tahap validasi dan epistemik belum tersentuh.

## Cara kerja engine saat ini
Sumber datanya adalah rating dari driving force berstatus APPROVED yang sudah punya status aksi, dalam rentang tanggal (default bulan berjalan) (`ForesightReportingService.php:35-51`). Nilai kosong dianggap 5.

| Keluaran | Rumus | Lokasi |
|---|---|---|
| Resilience Index | 50 + Σ: Decided Plan `+I×(11−U)/20`; Monitoring dengan I≥7 `−I×U/30`; dibatasi 0–100 | `ReasoningEngineService.php:56-79` |
| Growth Index | Hanya dimensi Customer, Technology, Economy: 50 + rata-rata (Decided Plan `I×1,5`, lainnya `I×0,5`) | `ReasoningEngineService.php:84-116` |
| Postur | 2×2 dengan ambang 70/70 pada Resilience dan Growth | `ReasoningEngineService.php:121-156` |
| Top 3 critical forces | Urut menurun `I + 10 (Monitoring) + 5 (High)` | `ReasoningEngineService.php:161-190` |
| EDA | Frekuensi per dimensi, tren bulanan (total, decided, monitoring, rata-rata I dan U) | `ReasoningEngineService.php:195-240` |

Endpoint: `GET /dashboard/reasoning-data` (`routes/web.php:33`). Ditampilkan oleh `ExecutiveOverview.jsx` dan `EdaCharts.jsx`.

## Celah dan bug
1. **Growth Index tidak bisa melewati 65.** Nilainya 50 + rata-rata, dengan rata-rata maksimal 10 × 1,5 = 15. Akibatnya postur "Agile & Resilient" dan "Growth-Aggressive (Exposed)" tidak pernah muncul (`ReasoningEngineService.php:100-114`, `:123-148`).
2. **Skala kedua indeks tidak sebanding.** Resilience dihitung sebagai jumlah, sehingga ikut naik atau turun seiring banyaknya force. Growth dihitung sebagai rata-rata. Ambang 70/70 yang sama untuk keduanya jadi tidak bermakna.
3. **Uncertainty diabaikan di Growth,** dan time horizon diabaikan di kedua indeks, padahal keduanya bagian inti metode foresight.
4. **Logika kausal tidak terlihat.** Pengguna hanya melihat angka dan satu kalimat postur, tanpa kontribusi per force atau aturan yang memicunya.
5. **Posisi titik radar acak.** `rand(2,4)`, `rand(5,6)`, dan `rand(7,8)` membuat titik berpindah setiap kali halaman dimuat. Kode juga mengandalkan ID dimensi yang berurutan dari 1 (`ForesightRadarResource.php:56-64`).
6. **Ekstraksi sinyal bukan AI.** Skor impact dan uncertainty diambil dari daftar kata (8, 5, atau 3 dan 8, 5, atau 2), dan `confidence_score` bernilai tetap 0,88 atau 0,72 berdasarkan panjang teks (`SignalExtractionService.php:186-210`).
7. **Deteksi horizon "Mid Term" tidak pernah cocok,** karena kode mencari "Medium Term", sehingga hasilnya selalu jatuh ke Short Term (`SignalExtractionService.php:181`).
8. **Sinyal yang diterima kehilangan skornya.** `accept_create` membuat driving force tanpa rating, sehingga impact, uncertainty, dan horizon sinyal terbuang (`SignalIngestionController.php:172-195`).
9. **Tidak ada riwayat skor.** Rating berupa satu baris per force (`DrivingForce.php:76`) yang ditimpa saat diubah. Satu-satunya deret waktu adalah `action_reasons`.
10. **Status dicocokkan lewat nama teks** ('Decided Plan', 'Monitoring', 'high') dan sebagian resource memakai ID hardcode.
11. **Tren hampir selalu satu titik,** karena rentang default adalah bulan berjalan (`resources/js/Components/AnalysisFilters.jsx:13`).
12. **Label "Dimensi STEEP" keliru.** Taksonominya 4 makro + 4 dimensi bergaya Porter (`resources/js/Components/EdaCharts.jsx:173`).
13. **Test terlalu longgar.** `tests/Feature/Reasoning/ReasoningEngineTest.php` hanya memeriksa batas (misalnya growth > 50), tanpa nilai pasti atau label postur, sehingga bug nomor 1 lolos.

## Kesiapan data untuk pattern recognition
K-Means, PCA, dan Decision Tree belum layak dipakai sekarang:
- **Volume.** Seed berisi 19 driving force. Clustering dengan segelintir titik tidak stabil, dan PCA pada sekitar empat fitur tidak mengungkap apa-apa.
- **Fitur.** Tiap force hanya punya dimensi, horizon, impact, dan uncertainty.
- **Label hasil.** Belum ada target yang bisa dipelajari, misalnya apakah force akhirnya terjadi, ditutup karena apa, atau efektif tidaknya aksi.
- **Riwayat.** Skor ditimpa, sehingga perubahan dari waktu ke waktu (data paling berharga untuk pola) hilang.

Data yang perlu dikumpulkan dulu:
1. Riwayat rating: setiap perubahan skor tersimpan dengan waktu dan penilai.
2. Penilaian lebih dari satu orang (sudah direncanakan sebagai Delphi di `docs/roadmap/NEXT_EVOLUTION.md`).
3. Label hasil di tahap Closed: alasan penutupan yang terstruktur, bukan teks bebas.
4. Volume nyata di luar data seed.

## Roadmap
Polanya mengikuti PDF: Data → Reasoning → Prototype → Refinement.

### Fase 1: fondasi yang benar dan terjelaskan (tanpa ML)
**1a. Engine indeks**
- Kedua indeks dinormalisasi menjadi rata-rata berbobot 0–100 dengan skala yang sama, tidak bergantung pada jumlah force.
- Growth memakai impact dan peluang (1 − uncertainty ternormalisasi). Resilience memakai bobot mitigasi dibanding eksposur. Horizon menjadi faktor bobot, misalnya Short Term lebih berbobot untuk Resilience.
- Ambang postur dikalibrasi supaya keempat postur bisa tercapai. Rumus dan bobot ditulis di dokumen produk.
- Perubahan di `app/Services/ReasoningEngineService.php`.
- Selesai bila: setiap postur punya kasus uji yang mencapainya, dan test memeriksa nilai pasti.

**1b. Causal reasoning yang terjelaskan**
- Endpoint mengembalikan, untuk tiap indeks, kontribusi per force (`+3,2` atau `−1,8`) beserta aturan yang memicunya, misalnya "Monitoring, impact 9 ≥ 7 → eksposur risiko".
- Overview menampilkan panel "Mengapa skor ini", berisi daftar faktor teratas dalam bentuk aturan seperti cabang decision tree.
- Kecocokan status lewat teks diganti ke ID atau enum (`app/Enums`).
- Perubahan di `ReasoningEngineService.php`, `DashboardController.php`, `resources/js/Components/ExecutiveOverview.jsx`.
- Selesai bila: jumlah kontribusi sama dengan selisih indeks dari baseline, dan e2e memeriksa panel penjelasan.

**1c. EDA lengkap (empat jenis analisis PDF)**
- **Distribusi:** histogram impact dan uncertainty.
- **Hubungan:** scatter impact × uncertainty dengan koefisien korelasi (Pearson), menggunakan ulang `PrioritizingChart.jsx`.
- **Perbandingan:** rentang atau box impact per dimensi dan per horizon.
- Rentang default grafik tren diperpanjang (misalnya 12 bulan terakhir), dan label "Dimensi STEEP" diganti menjadi "Dimensi Makro & Mezzo".
- Perubahan di `ReasoningEngineService.php` (method EDA baru dan parameter `$dateRange` yang kini benar-benar dipakai) dan `resources/js/Components/EdaCharts.jsx`.
- Selesai bila: setiap grafik punya test data yang nilainya diperiksa.

**1d. Rantai data yang tidak membuang informasi**
- Posisi radar deterministik, diturunkan dari skor dan horizon tanpa `rand()`, dan tidak lagi mengandalkan ID dimensi berurutan (`ForesightRadarResource.php`).
- Deteksi horizon diperbaiki ke "Mid Term" (`SignalExtractionService.php:181`).
- `accept_create` membuat `DrivingForceRating` dari skor dan horizon sinyal (`SignalIngestionController.php`).
- Tabel riwayat rating baru (`driving_force_rating_histories`), diisi oleh observer setiap kali skor berubah. Ini fondasi Fase 2.
- Selesai bila: radar stabil antar reload, test riwayat tercatat, dan test `accept_create` memeriksa rating.

### Fase 2: validasi empiris ringan
- Korelasi dan tren dari riwayat rating: apakah impact naik di dimensi tertentu, dan seberapa cepat force berpindah dari Monitoring ke Decided Plan.
- Korelasi dampak lintas kuadran (INT-013 di `docs/roadmap/FEATURE_INTEGRATION_ROADMAP.md`) sebagai bentuk awal causal loop.
- Clustering sederhana hanya bila volume sudah memadai (pedoman kasar: ratusan force dengan riwayat).

### Fase 3: hipotesis dan GenAI
- LLM adapter untuk ekstraksi sinyal (rencana di `docs/roadmap/NEXT_EVOLUTION.md`), dengan kutipan bukti wajib dan keputusan akhir tetap di tangan manusia.
- Causal loop antar driving force yang bisa diedit analis, lalu skenario 2×2.
- Input skenario ("bagaimana jika force X menjadi Decided Plan") yang menghitung ulang indeks, seperti mini-app di PDF.

### Fase 4: framework synthesis
- Theory reconciliation: bobot dan ambang dicocokkan dengan literatur foresight (Three Horizons, analisis STEEP/PESTEL, Five Forces) dan dengan hasil historis.
- Kalibrasi bobot dari data (misalnya Decision Tree dari label hasil Fase 1d dan 2), lalu dikunci sebagai prinsip keputusan yang terdokumentasi.

## Risiko dan keputusan terbuka
- **Angka berubah.** Normalisasi indeks akan mengubah skor yang selama ini dilihat pengguna. Perlu catatan rilis dan mungkin periode tampilan berdampingan.
- **Bobot dan ambang adalah keputusan produk,** bukan teknis. Fase 1a memerlukan persetujuan product owner atas bobot awal.
- **Data seed tipis.** Kalibrasi Fase 1 dilakukan dengan kasus uji sintetis, bukan data nyata.
- **Ekspektasi "AI".** Fitur bernama AI saat ini berbasis aturan. Label di UI sebaiknya jujur ("berbasis aturan") sampai Fase 3.

## Referensi
- `docs/1765250420756.pdf`: AI Business Reasoning Engine Foundation (sumber kerangka)
- `docs/roadmap/NEXT_EVOLUTION.md`: LLM adapter, Delphi, cross-impact, skenario
- `docs/roadmap/FEATURE_INTEGRATION_ROADMAP.md`: INT-013 cross-impact correlation
- `docs/product/ASSESSMENT_METHODOLOGY.md`: rubrik skor 1–10 dan usulan skor komposit
- `app/Services/ReasoningEngineService.php`, `app/Services/ForesightReportingService.php`, `app/Services/SignalExtractionService.php`
- `tests/Feature/Reasoning/ReasoningEngineTest.php`, `tests/e2e/executive-reasoning.e2e.ts`
