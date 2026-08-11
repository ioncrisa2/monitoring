# Redesign UI Aplikasi — Enterprise, Ringkas, dan Konsisten

## Tujuan Redesign

Target utama redesign adalah menghasilkan tampilan aplikasi yang:

- Ringkas dan efisien untuk penggunaan operasional harian.
- Terlihat enterprise, bukan seperti generic SaaS template.
- Menghindari pola visual yang terasa seperti AI-generated UI atau “AI slop”.
- Memiliki hierarchy yang jelas.
- Konsisten dalam spacing, ukuran komponen, dan struktur layout.
- Menggunakan card hanya ketika memang memiliki fungsi.
- Memiliki proporsi komponen yang sesuai dengan jenis informasi.
- Membuat fungsi elemen UI mudah dikenali dan tidak ambigu.
- Nyaman digunakan dalam jangka panjang oleh pengguna administratif dan operasional.

Prinsip utamanya:

> Enterprise UI tidak harus penuh dekorasi. Prioritasnya adalah clarity, density, predictability, dan hierarchy.

---

# 1. Kurangi Penggunaan Card

Masalah utama pada desain saat ini adalah terlalu banyak informasi dibungkus menggunakan card.

Pola yang muncul:

```text
Page
└── Card
    └── Section
        └── Mini Card
            └── Badge / Pill
```

Hal ini membuat halaman terlihat seperti kumpulan widget dibanding sebuah aplikasi operasional yang terstruktur.

## Aturan Baru

Gunakan card hanya jika:

- Blok informasi benar-benar independen.
- Memiliki action sendiri.
- Merupakan KPI atau summary.
- Perlu dipisahkan dari konteks di sekitarnya.
- Merupakan surface interaktif seperti modal atau popover.

Untuk grouping informasi biasa, gunakan:

- Heading.
- Divider.
- Spacing.
- Grid.
- Background section bila benar-benar diperlukan.

## Contoh

Sebelum:

```text
Card Informasi
  └── Mini Card Ringkasan Keuangan

Card Timeline
  ├── Card Timeline
  ├── Card Timeline
  └── Card Timeline
```

Redesign:

```text
Informasi Pekerjaan
────────────────────────────────

Field                  Field
Field                  Field


Ringkasan Keuangan

Rp32 jt        Rp28 jt        Rp3 jt        Rp560 rb

────────────────────────────────

Riwayat Status

● 25 Apr   Selesai
│          Diubah oleh Bambang Supervisor
│
● 23 Apr   Cetak
│          Diubah oleh Budi Reviewer
│
● 22 Apr   Review
```

Target pengurangan penggunaan card:

**sekitar 40–50%.**

---

# 2. Dashboard Harus Lebih Ringkas

Dashboard saat ini memiliki terlalu banyak informasi sekaligus.

Beberapa elemen yang muncul antara lain:

- KPI operasional.
- KPI finansial.
- Alert SLA.
- Grafik pendapatan.
- Workflow.
- Analytical cards.
- Bottleneck.
- Conversion funnel.
- Informasi finansial lainnya.

Masalahnya bukan kekurangan informasi, tetapi tidak adanya prioritas yang kuat.

Dashboard utama seharusnya menjawab empat hal:

1. Bagaimana kondisi pekerjaan sekarang?
2. Apa yang bermasalah?
3. Apa yang membutuhkan tindakan?
4. Bagaimana tren utama?

## Struktur Dashboard yang Disarankan

```text
Dashboard Operasional                         [Semua Cabang ▼]

4 pekerjaan melewati SLA                      Lihat pekerjaan →

┌──────────────┬──────────────┬──────────────┬──────────────┐
│ Aktif        │ SLA Tepat    │ Overdue      │ Selesai      │
│ 5            │ 50%          │ 4            │ 0            │
└──────────────┴──────────────┴──────────────┴──────────────┘


Pipeline Pekerjaan                            SLA & Bottleneck
──────────────────────                       ─────────────────────
Persiapan        1                            Overdue         4
Survey           1                            Review lama     3
Pengerjaan       1                            Avg SLA        xx
Review           1
Cetak            1


Pekerjaan yang Membutuhkan Perhatian
────────────────────────────────────────────────────────────────

Kontrak      Klien      Status      Aging      SLA       PIC
...
```

## Analytics

Grafik berikut tidak harus berada di dashboard utama:

- Revenue chart.
- Conversion funnel.
- Analytics mendalam.
- Produksi per periode.
- Perbandingan keuangan.

Pindahkan ke menu seperti:

```text
Laporan Produksi
Analytics
Laporan Manajemen
```

Dashboard utama cukup untuk **operational awareness dan action**.

---

# 3. KPI Harus Memiliki Hierarchy

Saat ini beberapa KPI finansial dan operasional terlihat memiliki level kepentingan yang sama.

Padahal:

```text
Pekerjaan Aktif
WIP Rp195 juta
Overdue SLA
```

memiliki fungsi yang berbeda.

## Operational Health

```text
Aktif        SLA Compliance        Overdue        Selesai Bulan Ini

5            50%                   4              0
```

## Financial Summary

Tidak perlu semuanya menjadi floating card.

Gunakan summary yang lebih tenang:

```text
Nilai Penawaran       WIP                Realized

Rp84 jt               Rp195 jt           Rp59 jt
```

Operational KPI harus lebih dominan di dashboard utama.

---

# 4. Table Harus Lebih Flat

Halaman `Pekerjaan` sudah memiliki struktur yang cukup baik:

```text
Search
Filter
Table
Action
```

Yang perlu dikurangi adalah visual noise.

## Hindari Tombol Primary pada Setiap Row

Jangan gunakan:

```text
[ Detail & Process → ]
[ Detail & Process → ]
[ Detail & Process → ]
[ Detail & Process → ]
```

karena mata pengguna justru tertarik ke tombol dibanding isi tabel.

Gunakan action yang lebih tenang:

```text
Buka →
```

atau:

```text
→
```

atau contextual menu:

```text
•••
```

Primary button sebaiknya tidak diulang pada setiap row.

---

# 5. Hindari Emoji pada UI Operasional

Contoh:

```text
Hanya Overdue SLA 🔥
```

lebih baik menjadi:

```text
☐ Hanya SLA Terlewat
```

atau:

```text
☐ Overdue SLA
```

Emoji seperti api, bintang, rocket, sparkles, dan sejenisnya sering membuat UI terasa seperti SaaS marketing atau hasil generator.

Gunakan icon sistem bila memang diperlukan.

---

# 6. Pisahkan Workflow Status dan Semantic Status

Saat ini workflow memiliki banyak warna:

```text
Persiapan     Gray
Survey        Purple
Pengerjaan    Blue
Review        Yellow
Cetak         Cyan
Selesai       Green
```

Masalahnya adalah warna workflow bercampur dengan semantic colors.

## Semantic Color

Gunakan aturan:

```text
Green    = Success
Amber    = Warning
Red      = Danger / Overdue / Error
Blue     = Information
Gray     = Neutral
```

Workflow sebaiknya menggunakan satu family warna brand atau neutral.

Contoh:

```text
PERSIAPAN
SURVEY
PENGERJAAN
REVIEW
CETAK
SELESAI
```

Semua dapat menggunakan variasi neutral atau purple tint.

Dengan demikian:

```text
OVERDUE SLA
```

berwarna merah akan benar-benar menonjol.

---

# 7. Redesign Detail Pekerjaan

Struktur saat ini sudah cukup tepat:

```text
Header
Workflow
Tabs
Main Content
Aside
```

Yang perlu diperbaiki terutama presentasi komponennya.

## Workflow

Hindari step berbentuk kotak besar:

```text
[ PERSIAPAN ] → [ SURVEY ] → [ PENGERJAAN ] → ...
```

Gunakan stepper horizontal yang lebih compact:

```text
✓ Persiapan ─ ✓ Survey ─ ✓ Pengerjaan ─ ✓ Review ─ ✓ Cetak ─ ● Selesai
```

Tinggi ideal:

```text
40–48 px
```

Tidak perlu card besar atau shadow.

## Timeline

Jangan jadikan setiap riwayat sebagai card.

Gunakan:

```text
● 25 Apr 2026
│ Selesai
│ Diubah oleh Bambang Supervisor
│
● 23 Apr 2026
│ Cetak
│ Diubah oleh Budi Reviewer
│
● 21 Apr 2026
  Review
```

Flat timeline lebih compact dan lebih mudah discan.

---

# 8. Form Harus Mengikuti Arti Data

Form tidak boleh hanya mengikuti grid secara matematis.

Proporsi field seharusnya sesuai jenis data.

Contoh yang masih sesuai:

```text
Nomor Urut       Tanggal Penawaran       Cabang
```

Tetapi field keuangan sebaiknya tidak semuanya berupa input besar.

Contoh:

```text
Fee Penawaran          TA Operasional
[ Rp ............ ]    [ Rp ............ ]


──────────────────────────────────────────────

DPP                    PPN 11%                PPh 2%

Rp28.000.000           Rp3.080.000            Rp560.000
```

Jika DPP, PPN, dan PPh merupakan calculated values, jangan tampilkan seperti editable input.

## Prinsip

Editable:

```text
Fee Penawaran
[ Rp 32.000.000 ]
```

Calculated:

```text
DPP
Rp 28.000.000
```

Status:

```text
SELESAI
```

Action:

```text
[ Simpan Penawaran ]
```

Navigation:

```text
Penawaran →
```

Setiap bentuk komponen harus mengkomunikasikan fungsi.

---

# 9. Standard Ukuran dan Spacing

Gunakan sistem spacing yang konsisten.

## Spacing Scale

```text
4 px
8 px
12 px
16 px
24 px
32 px
```

Utamakan:

```text
8 / 16 / 24 / 32
```

## Recommended Size

| Komponen | Ukuran / Aturan |
|---|---|
| Page gutter | 24–32 px |
| Section gap | 24 px |
| Component gap | 12–16 px |
| Micro spacing | 4–8 px |
| Card radius | 8 px |
| Card padding | 20–24 px |
| Input height | 40 px |
| Primary button | 40 px |
| Table action | 32–36 px |
| Table row | 56–64 px |
| KPI card | ±100–112 px |
| Sidebar | ±232–240 px |
| Topbar | ±60–64 px |
| Modal small | 440–480 px |
| Modal standard | 560–640 px |
| Modal complex | 720–800 px |

---

# 10. Border Radius

Hindari radius besar pada hampir seluruh komponen.

Gunakan default:

```text
Card        8 px
Button      6–8 px
Input       6–8 px
Dropdown    8 px
Modal       10–12 px
Pill        Full rounded
Badge       Full rounded atau 6 px
```

Radius besar 12–20 px pada hampir semua komponen sering menghasilkan tampilan generic SaaS.

---

# 11. Kurangi Shadow

Enterprise back-office application tidak membutuhkan banyak floating surface.

Gunakan:

```css
border: 1px solid #E5E7EB;
box-shadow: none;
```

Struktur surface:

```text
Page
background: #F8F9FB

Primary surface
background: white
border: #E5E7EB
```

Shadow hanya untuk:

- Dropdown.
- Popover.
- Modal.
- Floating context menu.

Card biasa tidak perlu terlihat mengambang.

---

# 12. Purple Tetap Dipertahankan

Purple brand saat ini masih dapat dipertahankan.

Masalahnya adalah terlalu banyak elemen menggunakan purple.

Akibatnya tidak ada lagi perbedaan antara action utama dan action sekunder.

## Action Hierarchy

Primary:

```text
Simpan Penawaran
Assign PIC
Buat Penawaran
```

Secondary:

```text
Batal
Edit SLA
Ubah Data
```

Ghost:

```text
Ubah PIC
Lihat Semua
Detail
```

Destructive:

```text
Hapus
Batalkan Pekerjaan
Nonaktifkan
```

## Rule

Dalam satu visual area idealnya hanya terdapat:

**satu dominant primary action.**

---

# 13. Sidebar

Sidebar sekarang tidak perlu redesign total.

Strukturnya sudah cukup baik.

Yang perlu dibenahi terutama konsistensi naming.

Hindari campuran seperti:

```text
Pekerjaan / Job
Role & Hak Akses
Audit Trail & Logs
Executive Dashboard Analytics & Monitoring
Monitoring Pekerjaan (Work Orders)
```

Gunakan satu bahasa utama.

Contoh:

```text
Dashboard

OPERASIONAL
Penawaran
Pekerjaan
Laporan Produksi
Impor Data

ADMINISTRASI
Cabang
Pengguna
Peran & Hak Akses
Klien
Debitur
Jejak Audit
```

Istilah teknis seperti:

```text
SLA
PIC
DPP
PPN
PPh
```

boleh tetap digunakan.

---

# 14. Topbar

Teks seperti:

```text
KJPP Operational & Asset Monitoring System
```

tidak harus selalu muncul di topbar.

Pengguna sudah memahami aplikasi yang sedang digunakan.

Topbar dapat lebih compact:

```text
[KJPP Monitoring]                         System Admin ▼
```

Jika branding sudah ada di sidebar, topbar bahkan cukup berisi:

- Breadcrumb bila diperlukan.
- Notification.
- Profile.
- Account menu.

Dengan demikian ruang vertikal bisa digunakan untuk konten.

---

# 15. Master Data

Halaman master data seperti:

- Cabang.
- Pengguna.
- Klien.
- Debitur.

secara struktur sudah cukup baik.

Tetapi tidak perlu seluruh area:

```text
Filter + Search + Table
```

dibungkus outer card.

Gunakan flat layout:

```text
Master Data Cabang                       + Tambah Cabang

Kelola kantor cabang dan informasi terkait.

[Cari cabang...]

────────────────────────────────────────────────────────────

KODE     CABANG                    PIC             STATUS

JKT      Kantor Pusat Jakarta      12 pengguna     Aktif
...
```

Table yang flat biasanya terasa lebih enterprise dibanding table yang berada di dalam card besar.

---

# 16. Peran & Hak Akses

Halaman permission management membutuhkan density tinggi.

Jangan terlalu banyak card bersarang.

Gunakan layout dua kolom:

```text
Peran                         Hak Akses: System Admin

SYSTEM ADMIN                  Master Data
Supervisor                    ☑ Cabang
Admin                         ☑ Pengguna
Reviewer                      ☑ Klien
Surveyor                      ☑ Debitur

                              Pekerjaan
                              ☑ Penawaran
                              ☑ Pekerjaan
                              ☑ Survey
```

Rekomendasi:

```text
Role sidebar: ±260 px
Permission panel: flexible
```

Grouping permission cukup menggunakan section heading dan divider.

---

# 17. Modal

Buat sistem modal yang konsisten.

Jangan menentukan lebar modal secara acak berdasarkan halaman.

Gunakan tiga kategori.

## Small

```text
440–480 px
```

Untuk:

- Konfirmasi.
- Assign.
- Input singkat.
- Alert.

## Medium

```text
560–640 px
```

Untuk:

- Tambah cabang.
- Tambah pengguna.
- Form 5–8 field.
- Upload metadata.

## Large

```text
720–800 px
```

Untuk:

- Complex form.
- Multi-section input.
- Preview + form.

---

# 18. Welcome Page

Welcome page saat ini merupakan salah satu bagian yang paling terasa seperti generic SaaS landing page.

Pola seperti:

```text
Hero
Badge
CTA
Workflow cards
Feature cards
Illustration
```

lebih cocok untuk website marketing dibanding aplikasi internal.

Jika aplikasi memang internal, pertimbangkan menghilangkan welcome page.

Gunakan:

```text
KJPP Monitoring

Operational & Asset Monitoring System

[ Masuk ]
```

atau langsung:

```text
/ → /login
```

## Register

Jika akun dibuat oleh administrator, jangan tampilkan public registration.

Hapus:

```text
Register
Daftar
Create Account
```

dari halaman publik.

---

# 19. Login Page

Login page sekarang belum sepenuhnya menyatu dengan identitas produk.

Hindari tampilan yang terlalu menyerupai default framework starter.

Gunakan:

```text
KJPP Monitoring

Masuk ke Sistem

Gunakan akun perusahaan Anda.


Email
[............................]

Password
[............................]

☐ Ingat saya                    Lupa password?

[              Masuk              ]
```

Tidak perlu:

- Large illustration.
- Gradient dekoratif.
- Split screen.
- Gambar gedung.
- Banyak feature text.

Login harus sederhana dan profesional.

---

# 20. Typography

Typography sekarang masih dapat dipertahankan.

Tidak diperlukan perubahan font secara agresif.

Gunakan hierarchy seperti:

```text
Page Title       24 px / 600
Section Title    16 px / 600
Body             14 px / 400
Table            14 px / 400
Caption          12 px / 400
Label            12–13 px / 500
```

## Uppercase

Kurangi uppercase untuk heading panjang.

Hindari:

```text
INFORMASI PEKERJAAN & PENAWARAN
```

Gunakan:

```text
Informasi Pekerjaan & Penawaran
```

Uppercase tetap cocok untuk label kecil seperti:

```text
OPERASIONAL
ADMINISTRASI
MASTER DATA
```

---

# 21. Identifier dan Financial Number

Identifier panjang seperti nomor kontrak boleh menggunakan monospace.

Contoh:

```text
1/S.Kontrak/KJPP-HJA'R/0/IV/2026
```

Tetapi jangan gunakan monospace secara berlebihan pada konten biasa.

Untuk angka keuangan gunakan tabular numerals.

Contoh:

```text
Rp 195.000.000
Rp  59.000.000
Rp   3.080.000
```

Agar digit lebih mudah dibandingkan secara vertikal.

CSS:

```css
font-variant-numeric: tabular-nums;
```

---

# 22. Prinsip "Jelas dan Tidak Bias"

Komponen harus memiliki affordance yang jelas.

Pengguna tidak seharusnya bertanya:

- Apakah ini bisa diklik?
- Apakah ini bisa diedit?
- Apakah ini hanya informasi?
- Apakah ini tombol?
- Apakah ini status?
- Apakah ini link?

## Editable

```text
Fee Penawaran

[ Rp 32.000.000 ]
```

## Read Only / Calculated

```text
DPP

Rp 28.000.000
```

## Status

```text
SELESAI
```

## Navigation

```text
Lihat pekerjaan →
```

## Primary Action

```text
[ Simpan Penawaran ]
```

Jangan menggunakan card, pill, button, dan input hanya karena secara visual terlihat menarik.

Bentuk komponen harus mengikuti fungsi.

---

# 23. Hal yang Perlu Dihindari

Untuk menghindari visual yang terasa seperti AI-generated SaaS UI, hindari:

- Card pada hampir setiap section.
- Nested cards.
- Rounded card berlebihan.
- Shadow pada hampir semua container.
- Gradient yang tidak memiliki fungsi.
- Emoji dekoratif.
- Banyak badge tanpa arti.
- Semua action menggunakan primary color.
- Semua status menggunakan warna berbeda.
- Oversized KPI card.
- Feature-card buffet.
- Dashboard yang mencoba menunjukkan semuanya.
- Excessive microcopy.
- Terlalu banyak subtitle.
- Mixed Indonesian/English tanpa alasan.
- Button pada setiap table row.
- Input readonly yang terlihat editable.
- Empty whitespace tanpa hierarchy.
- Decorative illustration pada halaman operasional.
- Split-screen auth hanya untuk terlihat modern.

---

# 24. Design Tokens yang Disarankan

Contoh baseline:

```css
:root {
  --page-bg: #F8F9FB;
  --surface: #FFFFFF;

  --border: #E5E7EB;
  --border-strong: #D1D5DB;

  --text-primary: #111827;
  --text-secondary: #4B5563;
  --text-muted: #6B7280;

  --brand: #6D4AFF;
  --brand-hover: #5D3DE8;
  --brand-soft: #F2EFFF;

  --success: #15803D;
  --warning: #B45309;
  --danger: #B91C1C;
  --info: #1D4ED8;

  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;

  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-6: 24px;
  --space-8: 32px;
}
```

Nilai exact color dapat disesuaikan kembali dengan brand aplikasi.

Yang lebih penting adalah konsistensi penggunaan token.

---

# 25. Recommended Page Skeleton

Gunakan pola halaman yang konsisten.

## Index Page

```text
Page title                                     Primary Action
Description

Toolbar
Search                      Filter      Sort

────────────────────────────────────────────────────────────

Table / List

────────────────────────────────────────────────────────────

Pagination
```

## Detail Page

```text
Breadcrumb

Title                                         Primary Action
Metadata                                      Secondary Action

Status / Workflow

Tabs
────────────────────────────────────────────────────────────

Main Content                              Context Aside
```

## Form Page

```text
Breadcrumb

Title
Description

Section 1
────────────────────────────
Fields

Section 2
────────────────────────────
Fields

Section 3
────────────────────────────
Calculated Summary

────────────────────────────────────────────────────────────

                         Batal      Simpan
```

---

# 26. Prioritas Implementasi Redesign

Jangan redesign seluruh aplikasi sekaligus.

Gunakan urutan berikut.

## Prioritas 1 — Design Foundation

Tetapkan:

- Spacing.
- Radius.
- Button hierarchy.
- Typography.
- Color semantics.
- Border.
- Input height.
- Table density.
- Modal size.
- Card rule.

Ini harus selesai terlebih dahulu.

---

## Prioritas 2 — Core Components

Rapikan:

- Button.
- Input.
- Select.
- Badge.
- Status.
- Card.
- Table.
- Tabs.
- Modal.
- Dropdown.
- Breadcrumb.
- Stepper.

---

## Prioritas 3 — Layout

Standarkan:

- Sidebar.
- Topbar.
- Page container.
- Page heading.
- Toolbar.
- Form section.
- Detail layout.
- Master-data layout.

---

## Prioritas 4 — Pekerjaan

Redesign terlebih dahulu:

```text
Pekerjaan Index
Pekerjaan Detail
Workflow
Timeline
SLA
PIC
```

Ini salah satu workflow paling penting dalam aplikasi.

---

## Prioritas 5 — Penawaran

Fokus pada:

- Proporsi form.
- Editable vs calculated fields.
- Financial summary.
- CTA hierarchy.
- Field grouping.

---

## Prioritas 6 — Dashboard

Dashboard dikerjakan setelah komponen dasar matang.

Hindari mendesain dashboard terlebih dahulu karena dashboard merupakan kombinasi hampir seluruh design pattern aplikasi.

---

## Prioritas 7 — Master Data

Terapkan flat table pattern pada:

- Cabang.
- Pengguna.
- Klien.
- Debitur.
- Role.
- Permission.

---

## Prioritas 8 — Authentication

Terakhir, rapikan:

- Login.
- Welcome.
- Forgot password.
- Account states.

---

# 27. Checklist Evaluasi Setiap Screen

Sebelum screen dianggap selesai, cek:

### Hierarchy

- Apakah pengguna tahu informasi paling penting dalam 3 detik?
- Apakah title, action, dan status mudah ditemukan?
- Apakah primary action benar-benar terlihat sebagai primary?

### Density

- Apakah ada whitespace yang tidak memberikan hierarchy?
- Apakah informasi bisa dibuat lebih compact tanpa menurunkan readability?
- Apakah card benar-benar diperlukan?

### Component Semantics

- Apakah elemen clickable terlihat clickable?
- Apakah calculated value terlihat read-only?
- Apakah status terlihat sebagai status?
- Apakah navigasi terlihat sebagai navigasi?

### Consistency

- Apakah spacing mengikuti token?
- Apakah radius konsisten?
- Apakah table density sama?
- Apakah modal menggunakan size standard?
- Apakah heading mengikuti hierarchy yang sama?

### Enterprise Quality

- Apakah ada emoji dekoratif?
- Apakah ada gradient yang tidak diperlukan?
- Apakah terlalu banyak shadow?
- Apakah terlalu banyak pill?
- Apakah banyak warna bersaing?
- Apakah screen terlihat seperti generic SaaS template?

Jika ya, kurangi.

---

# 28. Ringkasan Aturan Inti

Gunakan aturan berikut sebagai baseline seluruh redesign:

1. Kurangi card sekitar 40–50%.
2. Gunakan card hanya untuk unit informasi independen.
3. Satu dominant CTA per visual area.
4. Purple hanya untuk primary action dan selected state.
5. Pisahkan workflow color dari semantic color.
6. Gunakan sistem spacing 8 / 16 / 24 / 32.
7. Radius default 8 px.
8. Border lebih dominan daripada shadow.
9. Shadow hanya untuk floating UI.
10. Table dibuat flat dan cukup dense.
11. Form mengikuti arti data, bukan sekadar grid.
12. Calculated value jangan dibuat seperti editable input.
13. Gunakan satu bahasa UI.
14. Hilangkan emoji dan dekorasi generik.
15. Dashboard fokus pada operational health dan actionable items.
16. Analytics mendalam dipindahkan ke laporan.
17. Auth dibuat minimal dan sesuai brand aplikasi.
18. Sidebar dipertahankan tetapi naming dirapikan.
19. Topbar dikurangi agar konten mendapat ruang lebih besar.
20. Semua komponen harus memiliki fungsi visual yang jelas.

---

# Target Akhir

Redesign tidak perlu mengubah seluruh identitas aplikasi.

Elemen yang masih dapat dipertahankan:

- Purple sebagai brand color.
- Struktur sidebar.
- Font existing.
- Pola navigasi utama.
- Struktur table.
- Banyak struktur halaman existing.

Perubahan utama sebaiknya difokuskan pada:

```text
Density
Hierarchy
Surface Usage
Spacing
Component Semantics
Action Priority
Color Semantics
Consistency
```

Target visual akhirnya adalah aplikasi enterprise back-office yang:

- Tidak terlihat seperti template.
- Tidak terlalu dekoratif.
- Mudah dipelajari.
- Cepat digunakan.
- Konsisten.
- Tidak melelahkan jika digunakan berjam-jam.
- Memiliki visual hierarchy yang stabil.
- Lebih menekankan fungsi daripada dekorasi.
