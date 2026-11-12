# Rencana migrasi: Ant Design → shadcn/ui + Radix UI + Tailwind CSS v4

Branch: `feat/shadcn-radix-migration` (dari `feat/executive-reasoning`)
Status: rencana dan estimasi, belum ada perubahan kode aplikasi.

## Latar belakang

Desain baru Foresight Radar dibuat sebagai canvas Design dan memakai design system Foresight Radar dengan pola shadcn/ui + Radix. Referensinya:

- Canvas: https://claude.ai/artifact/WJSBTApKKJJZEJGBQAWT62 (artboard halaman, Pustaka chart, Sheet detail sinyal, Handoff implementasi)
- Source artboard ada di `design/foresight-radar/project/`. Folder ini lokal dan tidak di-commit.

Frontend saat ini seluruhnya memakai Ant Design 5. Tailwind 3 terpasang, tapi direktifnya dikomentari, jadi tidak aktif.

## Inventaris saat ini

### Skala
- 39 dari 48 file di `resources/js` mengimpor antd, sekitar 11.400 LOC.
- Ada 43 komponen antd berbeda. Yang paling sering dipakai:

| Komponen | Jumlah file |
|---|---|
| Typography, Button | 28 |
| Select | 16 |
| Form | 15 |
| Tooltip | 14 |
| message | 13 |
| Table, DatePicker (RangePicker) | 11 |
| Popconfirm | 5 |
| Modal | 4 |
| Drawer | 3 |

- 33 file memakai `@ant-design/icons`. Design system menetapkan ikon Ant Outlined, jadi paket ini dipertahankan. Paket ini tree-shakeable dan bisa jalan tanpa antd.

### Bagian yang paling berat
- **8 tabel server-side.** Pagination, sort dan filter di server, data diambil lewat `axios` + `qs` + `route()` Ziggy. Tabelnya ada di halaman Approval, ClosedItems, DrivingForce, RatingUrgency, StatusAction, TimeHorizon, UserManagement/User, dan `Components/OverallStatus.jsx` (merge rowSpan + export `xlsx`).
- **Form antd dengan `rules`** ada di sekitar 15 file, kebanyakan di dalam `Components/Dialog.jsx`. Inertia `useForm` hanya dipakai di partial Profile.
- **RangePicker** dipakai di 11 file.
- **`Components/Radar.jsx`** (912 baris): chart polar ECharts, algoritma anti-collision, Drawer detail dan mode fullscreen.

### Theme dan CSS
- `ConfigProvider` dipasang di `Layouts/AuthenticatedLayout.jsx`. GuestLayout, Login, Register, Error dan Welcome masing-masing punya `ConfigProvider` sendiri.
- `resources/css/additional.css` (168 baris) berisi override `.ant-*` dan class yang dirujuk test.

### Chart
ECharts dipakai di Radar, EdaCharts, ExecutiveOverview dan PrioritizingChart.

### Kode dan dependency yang tidak terpakai
- Komponen: `Components/Navbar`, `Components/Sidebar`, `Components/Footer`.
- Sisa Breeze: `ApplicationLogo`, `Checkbox`, `DangerButton`, `InputError`, `InputLabel`, `PrimaryButton`, `TextInput`.
- Dependency: `@ant-design/plots`, `react-router-dom`, `@headlessui/react`.

### Build
- `Dockerfile` masih `node:18`, sedangkan Tailwind v4 butuh Node 20 atau lebih baru.
- `@tailwindcss/vite` kompatibel dengan Vite 5.

### Test
- **PHPUnit hampir tidak tersentuh.** `ContainerHardeningTest` mengecek baris `USER www-data` dan `chown` di Dockerfile, jadi keduanya wajib dipertahankan.
- **Test e2e yang akan patah:**

| File | Penyebab |
|---|---|
| `driving-force.e2e.ts` | `.ant-select-*`, `#basic_dimension_id`, `#basic_pic_id` |
| `visualizations.e2e.ts` | `.ant-select-item-option` |
| `responsive-navigation.e2e.ts` | `aside.ant-layout-sider` |
| `accessibility-labels.e2e.ts` | id `login_*` buatan antd Form |
| `color-contrast.e2e.ts` | `rgb(89,89,89)`, `.custom-green-button`, `.custom-tag` |

## Keputusan teknis

### Tailwind CSS v4 (CSS-first)
- `resources/css/app.css` berisi `@import "tailwindcss"` dan `@theme`.
- Plugin `@tailwindcss/vite` ditambahkan di `vite.config.js`.
- Yang dihapus: `tailwind.config.js`, entri tailwind di `postcss.config.js`, dan `@tailwindcss/forms`.
- **Selama koeksistensi dengan antd:** preflight dimatikan. Caranya, import layer `theme` dan `utilities` secara manual tanpa `preflight`. Preflight diaktifkan lagi di fase terakhir.

### shadcn/ui
- Pakai CLI terbaru, yang sudah mendukung Tailwind v4, dengan `tsx: false`.
- Di `components.json`, alias diarahkan ke `@/Components` dan `@/Components/ui`. Ini wajib: di Windows dan macOS, folder `components` dan `Components` dianggap sama.

### Token design system
- CSS variable diambil dari `tokens.json` design system Foresight Radar, untuk light dan `.dark`.
- Variabel itu dipetakan ke variabel shadcn: `--background`, `--foreground`, `--card`, `--muted`, `--border`, `--input`, `--primary`, `--accent`, `--ring`, `--destructive`.
- Token khusus ikut ditambahkan: `--scan`, `--night`, `--dim-*`, `--horizon-*`, `--priority-*`.
- Radius: sm 4px, md 8px, lg 12px.
- Font Figtree dan JetBrains Mono dari Google Fonts menggantikan Bunny Fonts di `resources/views/app.blade.php`.

### Pustaka

| Kebutuhan | Pustaka |
|---|---|
| DataTable server-side generik | TanStack Table |
| Form dan validasi (shadcn Form) | react-hook-form + zod |
| Calendar range | react-day-picker. `dayjs` tetap dipakai untuk format dan query supaya perubahan kecil. |
| Toast (ganti `message`/`notification`) | Sonner |
| Chart standar | Recharts lewat shadcn Charts |
| Radar 360° | Komponen SVG custom. `computeAntiCollisionPoints` dipindah ke `radarLayout.js`. |
| Ikon | `@ant-design/icons` (Outlined) |

### Strategi
- antd dan shadcn berjalan berdampingan.
- Migrasi dilakukan per halaman, dengan satu PR per fase.
- antd baru dihapus di fase terakhir.

## Fase dan estimasi

Estimasi untuk 1 developer, dalam hari kerja.

| Fase | Isi | Estimasi |
|---|---|---|
| 0 · Fondasi | Node 22 di Dockerfile, Tailwind v4 + plugin Vite, shadcn init, token CSS + dark mode, font, `lib/utils.js`, ThemeProvider | 1,5–2 |
| 1 · Shell & primitif | AuthenticatedLayout baru (Sidebar + Sheet di mobile, header, user menu) dan GuestLayout. Komponen bersama: `DataTable` (server pagination/sort/filter), `DateRangePicker`, `FilterBar`, `PageHeader`, `ConfirmDialog` (AlertDialog, pengganti Popconfirm), `FormDialog` (pengganti `Components/Dialog.jsx`), `StatusBadge`/`PriorityBadge`, toast | 4–5 |
| 2 · Halaman CRUD tabel | DrivingForce (+ Form, Drawer → Sheet), TimeHorizon, RatingUrgency, StatusAction (+ Timeline), Approval, ClosedItems, User Management | 6–8 |
| 3 · Laporan & visualisasi | ForesightRadar (RadarScope SVG + Sheet detail), Prioritizing (matriks), RegisteredList + OverallStatus (rowSpan + xlsx), Dashboard + ExecutiveOverview + EdaCharts (Recharts) | 5–7 |
| 4 · Signals | Signals/Index: Tabs, 2 form, Descriptions, 3 dialog aksi | 1,5–2 |
| 5 · Auth, Profile, Error, Welcome | 6 halaman Auth, 4 Profile, Error, Welcome | 3 |
| 6 · Pembersihan & QA | Update selector e2e ke role/label/`data-testid`, sesuaikan `color-contrast.e2e.ts` dengan token baru, hapus antd + `additional.css` + ECharts + kode mati, aktifkan preflight, cek visual light/dark/mobile, audit a11y | 2–4 |
| **Total** | | **≈ 23–31 hari kerja (~5–6 minggu)** |

## Risiko
- **Paritas DataTable server-side dengan antd Table** (sorter, filter, pagination, rowSpan). Mitigasinya: buat satu `DataTable` generik dulu, lalu uji di DrivingForce sebelum dipakai di halaman lain.
- **Konflik CSS selama koeksistensi.** Mitigasinya: preflight off sampai fase 6, dan hindari selector global.
- **Upgrade image Docker** (Node 18 → 22). Baris `USER www-data` dan `chown` harus tetap ada.
- **Test e2e kontras warna** saat ini hardcode nilai antd. Test ini perlu ditulis ulang berdasarkan token design system.
- **Diff sangat besar.** Mitigasinya: satu PR per fase dan review per halaman.

## File kunci
- Build dan konfigurasi:
  - `vite.config.js`, `package.json`, `postcss.config.js`, `jsconfig.json`
  - `tailwind.config.js` (dihapus), `components.json` (baru)
  - `Dockerfile`
- CSS dan view: `resources/css/app.css`, `resources/css/additional.css`, `resources/views/app.blade.php`
- Layout: `resources/js/Layouts/AuthenticatedLayout.jsx`, `resources/js/Layouts/GuestLayout.jsx`
- Komponen: `resources/js/Components/Dialog.jsx`, `resources/js/Components/Radar.jsx`, `resources/js/Components/OverallStatus.jsx`
- Halaman: `resources/js/Pages/*/Table.jsx` dan `resources/js/Pages/*/Form.jsx`
- Test: `tests/e2e/*.e2e.ts`

## Definition of done per fase
- `npm run build` berhasil di container `laravel_app`.
- `php artisan test` hijau.
- e2e terhadap `http://127.0.0.1:8080` hijau, dengan selector yang sudah diperbarui untuk halaman yang dimigrasi.
- Cek visual halaman yang dimigrasi dibanding artboard canvas, di light/dark dan desktop/mobile.
- Kontras teks minimal 4,5:1, navigasi keyboard berjalan, dan animasi berhenti saat `prefers-reduced-motion`.
