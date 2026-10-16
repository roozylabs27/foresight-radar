# Reasoning Engine: rumus, bobot, dan ambang

Dokumen ini mencatat keputusan product owner untuk Growth Index, Resilience Index, dan postur kesiapan di Overview (Fase 1a dari `docs/plans/reasoning-engine-framework.md`). Bobot awal disetujui pada 16 Oktober 2026. Implementasinya ada di `app/Services/ReasoningEngineService.php`, dan nilai pastinya diuji di `tests/Feature/Reasoning/ReasoningEngineTest.php`.

Engine ini berbasis aturan, bukan machine learning.

## Data masukan
Yang dihitung adalah rating milik driving force berstatus APPROVED yang sudah punya status aksi dan dibuat dalam periode filter (default: bulan berjalan). Skor kosong dianggap 5.

Skor dinormalisasi ke 0–1:
- **I** = impact / 10
- **U** = (uncertainty − 1) / 9

## Bentuk indeks
Kedua indeks memakai bentuk dan skala yang sama, 0–100 dengan baseline 50:

```
indeks = 50 + Σ(w · d) / Σw
```

- **d** adalah skor aturan untuk satu force, antara −50 dan +50.
- **w** adalah bobot time horizon force tersebut.
- **Kontribusi** satu force = w · d / Σw. Jumlah semua kontribusi sama dengan indeks dikurangi 50. Nilai inilah yang ditampilkan di panel "Mengapa skor ini?".

Karena berupa rata-rata berbobot, indeks tidak ikut naik atau turun hanya karena jumlah force bertambah.

## Resilience Index
Dihitung dari semua dimensi.

| Kondisi | d | Arti |
|---|---|---|
| Decided Plan | +50 · I · (1 − U/2) | Mitigasi terencana; lebih kuat bila risikonya jelas |
| Monitoring, impact ≥ 7 | −50 · I · U | Eksposur risiko yang belum dimitigasi |
| Monitoring, impact < 7 | 0 | Netral, tetapi tetap ikut dalam rata-rata |

Bobot horizon: Short Term 1,0 · Mid Term 0,75 · Long Term 0,5. Ancaman jangka pendek lebih menentukan ketahanan saat ini.

## Growth Index
Dihitung hanya dari dimensi peluang: **Economy, Technology, Customer**. Bila tidak ada force di dimensi ini, nilainya 50.

| Kondisi | d | Arti |
|---|---|---|
| Decided Plan | +50 · I · (1 − U) | Peluang ditangkap; makin pasti, makin besar |
| Monitoring | −25 · I · U | Peluang belum ditangkap |

Bobot horizon: 1,0 untuk semua horizon.

## Postur kesiapan
Ambangnya 60 untuk kedua indeks, setara dengan rata-rata kontribusi ≥ +10.

| | Growth ≥ 60 | Growth < 60 |
|---|---|---|
| **Resilience ≥ 60** | Agile & Resilient | Defensive Prepared |
| **Resilience < 60** | Growth-Aggressive (Exposed) | Vulnerable (Needs Attention) |

Keempat postur bisa tercapai. Masing-masing punya kasus uji dengan nilai pasti.

## Dampak ke angka yang sudah dilihat pengguna
Rumus lama membuat Growth paling tinggi 65, sedangkan Resilience berupa jumlah yang bergantung pada banyaknya force. Dengan rumus baru, database lokal per 16 Oktober 2026 (19 force aktif) menghasilkan Resilience 66,8 dan Growth 56,0, atau postur Defensive Prepared. Perubahan ini perlu disebut di catatan rilis.

## Mengubah bobot
Bobot dan ambang berupa konstanta di `ReasoningEngineService` (`RESILIENCE_THRESHOLD`, `GROWTH_THRESHOLD`, `EXPOSURE_IMPACT`, `GROWTH_DIMENSIONS`, `RESILIENCE_HORIZON_WEIGHTS`, `GROWTH_HORIZON_WEIGHTS`). Setiap perubahan harus memperbarui dokumen ini dan nilai yang diharapkan di test.

## Data pendukung
- **EDA**: frekuensi per dimensi, distribusi skor 1–10, korelasi Pearson impact × uncertainty, dan sebaran impact (min, Q1, median, Q3, max) per dimensi dan per horizon. Grafik tren mencakup minimal 12 bulan terakhir sampai akhir periode filter.
- **Riwayat rating**: `driving_force_rating_histories` menyimpan satu baris saat rating dibuat dan satu baris lagi setiap kali horizon, status aksi, prioritas, impact, atau uncertainty berubah (`DrivingForceRatingObserver`). Rating yang sudah ada sebelum tabel dibuat mendapat satu baris `baseline`. Data ini menjadi bahan Fase 2.

## Fase 2: validasi empiris ringan
Keputusan desain disetujui pada 16 Oktober 2026. Implementasinya ada di `app/Services/RatingHistoryAnalyticsService.php` dan `app/Services/CrossImpactService.php`, dengan nilai pasti diuji di `tests/Feature/Reasoning/RatingHistoryAnalyticsTest.php` dan `CrossImpactTest.php`.

### Tren skor dari riwayat rating
Untuk setiap bulan di jendela tren (minimal 12 bulan), engine mengambil skor terakhir yang tercatat untuk tiap rating sampai akhir bulan itu, lalu merata-ratakan impact dan uncertainty, baik untuk semua dimensi maupun per dimensi. Perubahan dihitung dari bulan pertama ke bulan terakhir yang berisi data.

Grafik baru ditampilkan bila ada minimal **2 bulan berisi data** dan **setidaknya satu perubahan skor**. Kalau belum, dashboard menyebut sejak kapan riwayat tercatat dan berapa perubahan yang sudah ada. Riwayat di database lokal baru dimulai 10 Oktober 2026, jadi saat ini yang tampil adalah keadaan kosong itu.

### Kecepatan Monitoring → Decided Plan
Untuk setiap force, engine menggabungkan keputusan status aksi (`action_reasons`) dengan perubahan status di riwayat rating, lalu menghitung hari dari keputusan Monitoring pertama sampai Decided Plan pertama sesudahnya. Hanya perpindahan yang terjadi di jendela tren yang dihitung. Force yang masih Monitoring ditampilkan terpisah, dengan lama menunggu sampai hari ini. Baris `baseline` tidak dipakai karena waktunya adalah waktu migrasi, bukan waktu keputusan.

### Cross-impact (INT-013, versi terkecil)
- **Model**: analis menilai pengaruh berarah "A terhadap B" dengan skor bulat −3 (sangat menekan) sampai +3 (sangat memperkuat). A→B dan B→A dinilai terpisah. Pasangan tanpa nilai berarti 0, dan menyimpan 0 tanpa catatan menghapus pasangan itu. Hanya driving force berstatus APPROVED yang masuk matriks. Data disimpan di tabel `cross_impacts`.
- **Ringkasan (metode Vester)**: Aktif = Σ|skor| di baris (seberapa kuat force memengaruhi), Pasif = Σ|skor| di kolom (seberapa kuat ia dipengaruhi). Keduanya dibandingkan dengan rata-rata matriks:

| | Pasif > rata-rata | Pasif ≤ rata-rata |
|---|---|---|
| **Aktif > rata-rata** | Kritis | Pendorong |
| **Aktif ≤ rata-rata** | Indikator | Penyangga |

  Force tanpa pasangan bernilai berstatus "Belum dinilai". Saat matriks masih jarang terisi, rata-ratanya kecil sehingga hampir semua force yang dinilai jatuh ke Kritis atau Pendorong. Klasifikasi baru bermakna setelah sebagian besar pasangan penting dinilai.
- **Tampilan**: halaman Cross-Impact di menu visualisasi berisi matriks dan ringkasan. Di Foresight Radar, tombol Cross-Impact menampilkan panah antar force yang sedang tampil: hijau memperkuat, merah putus-putus menekan, makin tebal makin kuat.
- **Hak akses**: `view-cross-impact` untuk developer, super-admin, admin, dan bod. `manage-cross-impact` untuk developer, super-admin, dan admin.

### Clustering: belum dibuat
K-Means atau clustering lain baru dibuat bila ketiga syarat ini terpenuhi:
- **Volume**: minimal **150 driving force APPROVED** dengan rating lengkap. Dengan 4 fitur (impact, uncertainty, horizon, dimensi), pedoman umum 10–20 titik per fitur per cluster untuk 3–4 cluster menghasilkan kebutuhan sekitar 120–320 titik.
- **Riwayat**: minimal **6 bulan riwayat** dengan perubahan skor, supaya fitur dinamis (laju perubahan impact, kecepatan keputusan) ikut dipakai.
- **Stabilitas**: cluster harus stabil, yaitu rata-rata koefisien silhouette ≥ 0,5 dan susunan cluster hampir sama saat 20% data diacak ulang. Kalau tidak, cluster hanya mencerminkan noise.

Database lokal saat ini berisi sekitar 19 force dan riwayat beberapa hari, jauh di bawah ketiga syarat. Clustering sekarang hanya akan menghasilkan kelompok yang berubah setiap ada satu force baru.

## Fase 3: hipotesis dan GenAI
Rencana dan keputusannya ada di `docs/plans/reasoning-engine-phase-3.md`. Integrasi LLM (ekstraksi sinyal dan narasi skenario) dijelaskan di `docs/product/LLM_INTEGRATION.md`. Dua bagian di bawah ini tidak memakai LLM.

### Causal loops
Loop adalah rantai pengaruh di matriks cross-impact yang kembali ke force awal, misalnya A → B → C → A (`app/Services/CausalLoopService.php`).

- **Pencarian**: setiap siklus elementer sampai **5 force**. Hanya pasangan dengan skor ≠ 0 yang dihitung. Pencarian dari force ke-i hanya melangkah ke force berindeks lebih besar, sehingga satu siklus ditemukan sekali, tanpa duplikat rotasi. Arah sebaliknya (A → C → B → A) dihitung sebagai loop lain.
- **Batas**: maks 300 loop dan 200.000 langkah. Bila salah satu tercapai, hasil ditandai terpotong dan UI menyebutnya.
- **Polaritas**: jumlah pengaruh negatif genap menghasilkan loop **Penguat (R)**, yang mendorong perubahan makin besar. Jumlah ganjil menghasilkan **Penyeimbang (B)**, yang meredam perubahan.
- **Kekuatan**: rata-rata geometris |skor| di sepanjang loop, dibagi 3, sehingga bernilai 0..1. Contoh: loop A ⇄ B dengan skor 2 dan 3 berkekuatan √6 / 3 = 0,816.
- **Urutan**: kekuatan menurun, lalu panjang menaik.
- Loop dikirim bersama matriks (`matrix()['loops']`) dan ikut diperbarui setiap kali skor diubah. Di halaman Cross-Impact, memilih satu loop menyorot selnya di matriks.

### Simulasi what-if
Tab "Simulasi What-if" di dashboard menghitung ulang Growth, Resilience, dan postur bila status aksi atau skor beberapa force diubah (`app/Services/ScenarioSimulationService.php`, `POST /dashboard/simulate`).

- **Hanya baca**: rating disalin di memori dan tidak pernah disimpan. Test memastikan tidak ada query tulis.
- **Efek langsung saja**: mengubah satu force hanya menggeser kontribusinya sendiri. Bobot horizon tidak berubah, jadi selisih indeks sama dengan jumlah selisih kontribusi force yang diubah (sebelum pembulatan). Cross-impact belum dirambatkan, karena skor −3..+3 belum punya pemetaan terkalibrasi ke impact atau uncertainty. UI menyatakan ini di banner.
- **Cakupan**: force yang diubah harus ada dalam periode dan dimensi yang dipilih; jika tidak, respons 422. Maks 50 override per permintaan.
- **Contoh** (kasus "Vulnerable" di test): mengubah force Monitoring dengan impact 9 dan uncertainty 8 menjadi Decided Plan menaikkan Resilience 50,3 → 81,5 dan Growth 56,8 → 70,6, sehingga postur berubah dari Vulnerable menjadi Agile & Resilient.
