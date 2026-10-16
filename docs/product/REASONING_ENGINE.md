# Reasoning Engine: rumus, bobot, dan ambang

Dokumen ini mencatat keputusan product owner untuk Growth Index, Resilience Index, dan postur kesiapan di Executive Overview (Fase 1a dari `docs/plans/reasoning-engine-framework.md`). Bobot awal disetujui pada 16 Oktober 2026. Implementasinya ada di `app/Services/ReasoningEngineService.php`, dan nilai pastinya diuji di `tests/Feature/Reasoning/ReasoningEngineTest.php`.

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
