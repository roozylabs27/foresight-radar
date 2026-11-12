# Rencana: dark theme sesuai design system Foresight Radar

## Status
Diimplementasikan dengan revisi dari user: **default `light`**, dan dark diaktifkan lewat **switch "Mode gelap"**. Tidak ada opsi "Sistem", dan preferensi OS diabaikan.
- **User menu:** baris switch (`menuitemcheckbox`) yang menjaga menu tetap terbuka.
- **Halaman tamu, Error, dan Welcome:** switch berdiri sendiri (`role="switch"`, label "Mode gelap").

Rekomendasi lain tetap dijalankan: fullscreen radar `night`, halaman error Blade dibangun ulang, dan isian input = `card`. Keputusan 1 dan 2 di bawah digantikan revisi ini.

Tambahan di luar rencana awal: dialog, alert dialog, dan sheet memakai `bg-popover` (surface-100), bukan `bg-background`. Sheet detail radar ikut `dark` saat dibuka dari fullscreen.

## Latar belakang
Migrasi antd → shadcn/ui (Fase 0–6) sudah selesai. Semua komponen sekarang memakai token CSS, dan blok `.dark` di `resources/css/app.css` sudah ada. Namun dark theme belum bisa dipakai pengguna:
- `ThemeProvider` default `light`, dan tidak ada UI untuk memanggil `setTheme`.
- Kelas `.dark` baru dipasang di `useEffect`, setelah halaman pertama kali tampil.

Design system "Foresight Radar" (artifact claude.ai, `project/tokens.json`) menyebut dark theme sebagai **tema kelas satu**, dengan beberapa aturan:
- Mode presentasi di proyektor sebaiknya memakai dark.
- Mode presentasi radar memakai latar `night` dengan label `on-night`.
- Kontras teks minimal 4,5:1 di light dan dark.
- Header hanya berisi tombol menu dan user menu.

## Kondisi saat ini

### Sudah sesuai
- **Token warna:** semua token di `:root` dan `.dark` identik dengan `tokens.json`. Ini mencakup surface, ink, brand, night/scan, status + soft, horizon, dan dimensi `dim-*`. `chart-1..5` dan `sidebar-*` adalah turunan aplikasi dan sudah konsisten.
- **Kontras teks:** tidak ada token teks yang gagal 4,5:1 di dark.
  - `foreground` 15:1 dan `muted-foreground` 8,1:1 di atas card.
  - `subtle-foreground` 5,9:1 di atas card, 4,8:1 di atas accent.
  - `primary-foreground` di atas `primary` 7,4:1.
  - Status di atas `-soft`: 6,6–8,3:1.
  - `--input` (border kontrol) 3,6:1 di atas card.
- **Komponen React:** radar, chart (Recharts), ExecutiveOverview, dan DimensionChip memakai `var(--…)`, jadi ikut berganti tema. Sudah dicek visual di Dashboard, Radar, dan User Management dengan kelas `.dark`.
- **Toast:** Sonner sudah menerima tema dari `useTheme()`.

### Celah
| # | Celah | Lokasi |
|---|---|---|
| 1 | Tidak ada toggle tema | `AuthenticatedLayout.jsx` (UserMenu), layout tamu |
| 2 | Kilasan tema terang (FOUC) saat halaman dimuat ulang. Tidak ada script inline di `<head>`, dan kelas dipasang di `useEffect`. | `resources/views/app.blade.php`, `ThemeProvider.jsx` |
| 3 | Tidak ada `color-scheme`, sehingga scrollbar, autofill, dan kontrol native tetap terang | `app.css` |
| 4 | Bayangan hardcode bernuansa terang, tidak terlihat di dark. Design system punya `shadow-sm/md/lg` dengan nilai dark. | `FilterBar.jsx`, `GuestLayout.jsx`, `Error.jsx` |
| 5 | Warna progress bar Inertia `#346beb`: di luar brand dan 3,7:1 di atas card dark | `app.jsx` |
| 6 | Teks nav aktif di dark memakai ink, bukan brand seperti di design system | `--sidebar-accent-foreground` di `.dark` |
| 7 | Panel `bg-night` di halaman login nyaris sama dengan background dark (1,03:1), sehingga batas split hilang. Tile BrandMark di card dark juga lemah (1,13:1). | `AuthSplitLayout.jsx`, `BrandMark.jsx` |
| 8 | Fullscreen radar memakai `bg-background`, bukan `night` seperti mode presentasi di design system | `Radar.jsx` |
| 9 | Halaman error Blade (403/404/419/500/503 non-Inertia) punya `<style>` sendiri, dengan sisa warna antd dan font yang berbeda. Hanya ada versi terang, dan di light pun tidak sesuai design system. | `resources/views/errors/layout.blade.php` |
| 10 | Input shadcn `dark:bg-input/30` memberi isian #27344b, karena `--input` di sini adalah warna border tebal. Design system ingin isian input = surface-100. Masih terbaca (10,7:1). | `ui/input.jsx`, `textarea`, `select`, `button` outline, `tabs` |

## Keputusan (rekomendasi, perlu konfirmasi)
1. **Default `system`**, dengan pilihan tersimpan di `localStorage` (`foresight-theme`). Alasannya: design system memperlakukan dark sebagai tema kelas satu. Alternatifnya tetap `light` sampai ada keputusan produk.
2. **Toggle di user menu**, berupa radio group *Terang / Gelap / Sistem* di antara "Profil Pengguna" dan "Keluar". Ini sesuai aturan header di design system.
   - Di halaman tamu (login, register, lupa sandi, error): tombol ikon kecil di pojok kanan atas.
3. **Fullscreen radar memakai `night`** dengan label `on-night` di kedua tema, sesuai mode presentasi.
4. **Preferensi hanya di browser**, tidak disimpan ke DB per user. Sinkron antar-perangkat bisa menyusul bila diminta.
5. **Halaman error Blade dibangun ulang** di atas token yang sama (memuat CSS Vite dan script tema inline), supaya sesuai design system di kedua tema.

## Langkah

### 1. Infrastruktur tema (≈0,5 hari)
- Tambah script inline di `<head>` `app.blade.php`, sebelum `@vite`. Script membaca `foresight-theme` (default `system`) dan `prefers-color-scheme`, lalu memasang `class="dark"` dan `style.colorScheme` di `<html>`. Script harus dibungkus try/catch karena localStorage bisa tidak tersedia.
- `ThemeProvider`:
  - Default `system`.
  - Pakai `useLayoutEffect`.
  - Ekspos `resolvedTheme` (light/dark) untuk komponen yang butuh nilai konkret.
  - Set `document.documentElement.style.colorScheme`.
- `app.css`: `:root { color-scheme: light }` dan `.dark { color-scheme: dark }`.
- `app.jsx`: warna progress Inertia memakai nilai brand. Opsi pertama `getComputedStyle(...).getPropertyValue('--primary')` saat start. Opsi kedua satu warna yang lolos di kedua tema.

### 2. Toggle (≈0,5 hari)
- Komponen `ThemeMenu` (DropdownMenuRadioGroup) di `UserMenu`, dengan ikon lucide `SunIcon`, `MoonIcon`, dan `MonitorIcon`.
- Komponen `ThemeToggle` (tombol ikon dengan menu kecil) untuk `AuthSplitLayout`, `GuestLayout`, dan header `Error.jsx`.
- Aksesibilitas: label "Tema tampilan", `aria-checked` dari radio Radix, dan pengumuman perubahan lewat Sonner.

### 3. Token dan polesan visual (≈1 hari)
- **Bayangan:** tambah `--shadow-sm/md/lg` di `:root` dan `.dark` (nilai dari design system), petakan ke `@theme inline`. Ganti tiga bayangan hardcode (celah #4).
- **Nav aktif:** di `.dark`, `--sidebar-accent-foreground: #6aa5ff` (5,7:1 di atas accent).
- **Login dan BrandMark:**
  - Di dark, beri panel split `lg:border-r border-border`, atau ganti latar panel ke `card`.
  - Beri tile BrandMark `ring-1 ring-border` di dark.
- **Fullscreen radar:** latar `bg-night`, label dan garis ring pakai `on-night` (celah #8).
- **Isian input:** putuskan antara membiarkan `dark:bg-input/30` (terbaca) atau menyamakan dengan design system (isian = `card`), lewat override kecil di `ui/input.jsx`, `textarea.jsx`, dan `select.jsx`.
- **Band horizon radar** di dark berdekatan tonnya (short vs card 1,68:1, mid vs long 1,18:1). Pemisahnya adalah garis ring, jadi pastikan garis ring cukup tegas.

### 4. Halaman error Blade (≈0,5 hari)
- `errors/layout.blade.php`:
  - Muat `resources/css/app.css` lewat `@vite` dan script tema inline yang sama.
  - Susun ulang markup dengan kelas token, seperti `Pages/Error.jsx` (BrandMark, kartu, tombol).
  - Hapus `<style>` lama dan Bunny Fonts.
- Pastikan `errors.e2e.ts` tetap lolos (teks heading 404 dipertahankan).

### 5. QA (≈1 hari)
- **Cek visual light dan dark, desktop dan mobile** untuk semua halaman:
  - Dashboard, Signals, 7 halaman CRUD, Prioritizing, Registered List, Foresight Radar (termasuk fullscreen dan Sheet detail).
  - Profile, Login, Register, Lupa sandi, Error Inertia, dan error Blade.
- **Cek tidak ada kilasan terang:** muat ulang dengan tema gelap tersimpan, dengan throttle network.
- **Audit kontras ulang** setelah perubahan token, termasuk teks di atas `night` dan marker radar.
- **Keyboard:** toggle bisa dicapai dengan Tab, dibuka dengan Enter, dipilih dengan panah, ditutup dengan Escape.

## Test
- **e2e baru `tests/e2e/theme.e2e.ts`:**
  - Pilih "Gelap" di user menu, lalu `<html>` punya kelas `dark` dan tetap gelap setelah reload (localStorage).
  - Pilih "Sistem" dengan emulasi `prefers-color-scheme: dark`, lalu gelap.
  - Halaman login: toggle tamu bekerja.
- **`color-contrast.e2e.ts`:** tambah pengecekan token di mode dark (`--foreground`, `--muted-foreground`, `--subtle-foreground` di atas `--card`, ≥4,5:1).
- **PHPUnit:** tidak terpengaruh, kecuali bila halaman error Blade punya test. Cek `tests/Feature` untuk assertion HTML error.

## Estimasi
≈3,5 hari kerja (1 developer).

## Pertanyaan terbuka untuk product owner
1. Default `system` (rekomendasi) atau tetap `light`?
2. Toggle cukup di user menu, atau juga perlu tombol ikon di header?
3. Fullscreen radar selalu `night` (design system) atau mengikuti tema aktif?
4. Halaman error Blade dibangun ulang, atau dibiarkan statis terang?
5. Isian input di dark: biarkan default shadcn atau samakan dengan design system (`card`)?

## File kunci
- `resources/css/app.css`
- `resources/views/app.blade.php`
- `resources/views/errors/layout.blade.php`
- `resources/js/app.jsx`
- `resources/js/Components/ThemeProvider.jsx`
- `resources/js/Layouts/AuthenticatedLayout.jsx`
- `resources/js/Layouts/GuestLayout.jsx`
- `resources/js/Components/auth/AuthSplitLayout.jsx`
- `resources/js/Components/BrandMark.jsx`
- `resources/js/Components/Radar.jsx`
- `resources/js/Components/FilterBar.jsx`
- `resources/js/Pages/Error.jsx`
- `resources/js/Components/ui/{input,textarea,select}.jsx`
- `tests/e2e/theme.e2e.ts` (baru)
- `tests/e2e/color-contrast.e2e.ts`
