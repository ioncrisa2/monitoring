# Redesign UI Aplikasi — Enterprise, Ringkas, dan Konsisten

## Status Implementasi — 12 Agustus 2026

> **Status keseluruhan: implementasi visual utama selesai (estimasi ±92%).**
>
> Design foundation, shell aplikasi, workflow pekerjaan, Dashboard, Laporan Produksi, Penawaran, seluruh master data, hak akses, audit, impor, welcome, autentikasi, profil, dan halaman 403 sudah memakai sistem visual baru. Yang tersisa adalah verifikasi visual manual lintas viewport/browser, keputusan produk terkait account lifecycle, serta hardening behavior/security yang sengaja dipisahkan dari refactor visual. Implementasi sudah dipecah menjadi commit lokal berdasarkan fungsi dan **belum di-push**.

### Ringkasan Progres

| Area | Status | Progres | Keterangan |
|---|---|---:|---|
| Dashboard operasional | Selesai secara implementasi | 95% | KPI, ringkasan keuangan, pipeline, kesehatan SLA, alert, filter cabang, dan tabel perhatian sudah memakai foundation baru. Validasi visual manual masih diperlukan. |
| Laporan produksi / analytics | Selesai secara implementasi | 90% | Grafik pendapatan, funnel konversi, cakupan filter, ekspor, serta tabel produksi sudah distandardisasi dan memiliki regresi data. |
| Penawaran | Selesai secara implementasi | 95% | Halaman daftar, form buat/edit bersama, ringkasan keuangan, outcome badge, dan modal konversi sudah memakai pola baru serta dilindungi regression test. |
| Design foundation | Selesai | 100% | Token light/dark, warna semantic, spacing, radius, shadow, typography, focus state, surface, form, button, badge, modal, dropdown, dan table baseline sudah tersedia. |
| Komponen inti | Sebagian besar selesai | 95% | Button, input, select, textarea, modal, dropdown, navigation link, tabs, flash message, serta workflow/SLA/outcome/active badge sudah distandardisasi. |
| Layout, sidebar, dan topbar | Selesai secara implementasi | 90% | Sidebar 240 px, topbar 60 px, page offset, context breadcrumb, mobile drawer, focus trap, dan account menu sudah distandardisasi. Audit visual lintas viewport masih diperlukan. |
| Pekerjaan & detail pekerjaan | Sebagian besar selesai | 90% | Daftar, detail, workflow stepper, tabs, timeline, SLA, PIC, aset, laporan, dokumen, dan tujuh modal sudah memakai foundation baru. Pemeriksaan visual/manual dan follow-up behavior masih tersisa. |
| Master data & hak akses | Selesai secara implementasi | 95% | Cabang, debitur, organisasi/klien, pengguna, peran, dan permission sudah memakai pola tabel, filter, status, action, dan modal yang konsisten. |
| Audit aktivitas & impor data | Selesai secara implementasi | 90% | Filter dan tabel audit serta alur unggah, staging, preview, dan proses impor sudah memakai foundation baru. Hardening backend impor masih terpisah. |
| Welcome, autentikasi, profil, dan 403 | Selesai secara implementasi | 90% | Shell tamu, identitas aplikasi, seluruh account state, profil, danger zone, dan fallback 403 sudah distandardisasi. Registrasi publik sudah ditutup; keputusan verification/self-delete masih terbuka. |
| Regresi otomatis | Selesai untuk cakupan redesign | 95% | Suite penuh lulus 86 test dan 604 assertion. Pemeriksaan browser visual, keyboard, dan screen reader tetap perlu dilakukan manual. |

Persentase di atas adalah estimasi berdasarkan cakupan rencana pada dokumen ini, bukan hasil pengukuran otomatis.

### Detail yang Sudah Dikerjakan

#### 1. Dashboard operasional

File terkait:

- `app/Livewire/Dashboard.php`
- `resources/views/livewire/dashboard.blade.php`

Perubahan yang sudah diterapkan:

- Mengubah judul dari **Executive Dashboard Analytics & Monitoring** menjadi **Dashboard Operasional**.
- Menyederhanakan alert overdue menjadi baris peringatan yang lebih ringkas, tanpa emoji, animasi, tombol solid, dan dekorasi berlebih.
- Mempertahankan empat KPI operasional utama: pekerjaan aktif, SLA compliance, overdue SLA, dan selesai bulan ini.
- Menghapus emoji dari kartu KPI agar tampil lebih profesional dan mudah dipindai.
- Menggabungkan tiga metrik finansial menjadi satu financial summary yang lebih tenang: nilai penawaran aktif, WIP, dan nilai pekerjaan selesai.
- Menghapus widget operasional sekunder yang membuat dashboard terlalu padat, termasuk antrean survey, review, dan cetak.
- Memindahkan grafik tren pendapatan dan funnel konversi penawaran keluar dari dashboard utama.
- Menghapus query dan state dashboard yang tidak lagi dipakai setelah pemindahan analytics.
- Mempertahankan pipeline, SLA/bottleneck, dan pekerjaan yang membutuhkan perhatian sebagai fokus tindakan operasional.
- Memigrasikan page shell, heading, filter cabang, KPI, dan seluruh typography ke token `ui-*` tanpa mengubah state `selectedBranchId`.
- Menghapus shadow serta radius besar dari KPI; financial summary sekarang berupa definition list datar dengan divider.
- Memisahkan pipeline dan kesehatan SLA sebagai dua analytical surface yang benar-benar independen.
- Mengubah attention table ke `ui-table` dengan caption, scoped header, row key, fallback relasi, tabular numerals, dan accessible action label.
- Menambahkan loading feedback pada filter cabang dan empty state yang eksplisit.

#### 2. Pemindahan analytics ke Laporan Produksi

File terkait:

- `app/Livewire/Reports/ProductionReport.php`
- `resources/views/livewire/reports/production-report.blade.php`

Perubahan yang sudah diterapkan:

- Memindahkan perhitungan tren pendapatan realized dari dashboard ke laporan produksi.
- Menambahkan pilihan tren pendapatan bulanan dan tahunan.
- Memindahkan funnel konversi penawaran beserta win rate ke laporan produksi.
- Menjaga filter analytics tetap mengikuti cabang yang dipilih.
- Menambahkan visual grafik pendapatan dan breakdown status penawaran.
- Menghapus emoji pada tombol export Excel.
- Mengganti presentasi status dan SLA di tabel dengan komponen `status-badge` dan `sla-badge` agar lebih konsisten.
- Mengubah judul dan copy campuran bahasa menjadi **Laporan Produksi**, **Ekspor Excel**, dan istilah Indonesia yang konsisten.
- Memisahkan filter cabang sebagai scope seluruh halaman dari filter status/tanggal yang hanya berlaku pada tabel dan ekspor, sesuai perilaku query saat ini.
- Menggunakan satu primary action untuk ekspor lengkap dengan disabled/loading state.
- Mengubah grafik dan funnel menjadi analytical surface tanpa shadow; conversion rate dibuat netral agar warna semantic hanya dipakai pada outcome.
- Menambahkan pressed state pada pilihan bulanan/tahunan, label grafik, tooltip yang dapat dipicu keyboard, serta progress semantics pada funnel.
- Mengubah tabel produksi ke pola flat dengan filter field bersama, caption, scoped header, row key, fallback data, dan pagination kondisional.
- Menambahkan regression test untuk scope cabang, revenue bulanan/tahunan, outcome/conversion, perbedaan scope filter, dan empty state.

#### 3. Penawaran

File terkait:

- `resources/views/livewire/offers/create.blade.php`
- `resources/views/livewire/offers/index.blade.php`
- `resources/views/livewire/offers/partials/form-fields.blade.php`
- `resources/views/components/offer-outcome-badge.blade.php`
- `tests/Feature/OffersWorkflowTest.php`

Perubahan yang sudah diterapkan:

- Membedakan field editable dengan nilai hasil kalkulasi.
- Fee penawaran dan TA tetap ditampilkan sebagai input editable.
- DPP, PPN, dan PPh tidak lagi ditampilkan seperti input; nilainya sekarang berupa summary read-only.
- Mengurangi nested card pada bagian kalkulasi keuangan dan menggantinya dengan section divider.
- Memperbaiki proporsi layout keuangan agar input utama lebih dominan daripada hasil kalkulasi.
- Mengubah action **+ Jadikan Pekerjaan** dari tombol solid berulang menjadi text action **Jadikan Pekerjaan →** yang lebih tenang.
- Mengekstrak seluruh field Create/Edit ke satu shared partial agar binding, error, dan visual form tidak kembali berbeda.
- Mengubah halaman Create menjadi form page datar dengan breadcrumb, empat section bermakna, calculated `<dl>`, dan action footer yang konsisten.
- Mengubah index menjadi flat toolbar dan table tanpa outer card, lengkap dengan loading state, empty state yang mengikuti filter, caption, scoped header, dan row key.
- Menambahkan outcome badge semantic dengan label sentence case serta membatasi monospace hanya pada nomor penawaran dan kode.
- Memigrasikan modal edit dan konversi ke shared modal dengan ukuran 720 px dan 448 px, focus management, Escape/backdrop close, serta scroll lock.
- Menambahkan loading/disabled state pada simpan dan konversi serta accessible label pada seluruh row action.
- Menjaga seluruh kontrak `wire:model`, `save`, `edit`, `prepareConvert`, `convertToJob`, `wire:navigate`, dan `wire:confirm`.
- Menambahkan regression test untuk nomor otomatis, kalkulasi pajak, persist Create/Edit, kombinasi filter, modal state, dan konversi WorkOrder/StatusHistory.

#### 4. Design foundation dan komponen inti

File terkait:

- `tailwind.config.js`
- `resources/css/app.css`
- `resources/views/components/*.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`

Perubahan yang sudah diterapkan:

- Menetapkan token light dan dark untuk page background, surface, border, text, brand, success, warning, danger, dan info.
- Menetapkan radius standar 6 px, 8 px, dan 12 px serta raised shadow khusus modal dan dropdown.
- Menambahkan primitive layout `ui-page`, page header, section heading, surface, toolbar, table, dan modal.
- Menetapkan button hierarchy: primary, secondary, ghost, dan danger dengan tinggi minimum 40 px.
- Menetapkan field input/select/textarea setinggi minimum 40 px, tanpa shadow dekoratif, dengan focus state yang terlihat.
- Menambahkan table density baseline dengan header 44 px dan row minimum 56 px.
- Menambahkan semantic badge dan memisahkan workflow status dari success/danger status.
- Mengubah status workflow menjadi badge bersudut 6 px, bukan pill besar.
- Menstandardisasi modal ke kategori lebar 448, 576, 640, 720, dan 800 px.
- Menambahkan focus-visible global, smooth scroll, selection color, tabular numeral utility, dan dukungan `x-cloak`.
- Mempertahankan Figtree sesuai arahan redesign, lalu melengkapi font weight 400–800 untuk hierarchy yang lebih halus.
- Menambahkan shared component `select-input` dan `textarea-input`.
- Mempertahankan pipeline Tailwind v3 yang saat ini aktif; migrasi dependency tidak dicampurkan dengan pekerjaan visual.

#### 5. Daftar pekerjaan

File terkait:

- `resources/views/livewire/work-orders/index.blade.php`

Perubahan yang sudah diterapkan:

- Menghapus outer card besar yang sebelumnya membungkus toolbar, tabel, dan pagination.
- Menggunakan page header, toolbar, form control, table, serta badge dari foundation baru.
- Mengubah heading tabel menjadi sentence case dan merapikan density setiap row.
- Mempertahankan action row sebagai text action **Buka →**, bukan tombol primary berulang.
- Mengganti label **Hanya Overdue SLA** menjadi **Hanya SLA terlewat**.
- Menambahkan loading state ketika pencarian atau filter berubah.
- Menambahkan empty state yang menjelaskan tindakan berikutnya kepada pengguna.
- Menambahkan caption tabel, label tersembunyi untuk form control, `scope` pada header, `wire:key`, dan label tombol tutup untuk aksesibilitas.
- Menggunakan tabular numerals untuk tanggal dan aging serta fallback copy ketika relasi data tidak tersedia.

#### 6. Shell aplikasi, sidebar, dan topbar

File terkait:

- `resources/views/layouts/app.blade.php`
- `resources/views/livewire/layout/navigation.blade.php`
- `resources/views/components/app-navigation-links.blade.php`
- `resources/views/components/navigation-icon.blade.php`
- `resources/views/components/sidebar-link.blade.php`

Perubahan yang sudah diterapkan:

- Mengubah sidebar desktop dan mobile dari 256 px menjadi 240 px serta menyelaraskan offset main content dan topbar.
- Mengubah topbar menjadi 60 px dan menampilkan konteks halaman yang ringkas.
- Menggunakan token surface, border, text, canvas, dan brand pada seluruh shell aplikasi.
- Menghilangkan radius dan shadow berlebih pada sidebar, topbar, account trigger, serta theme switcher.
- Memusatkan daftar menu dalam satu shared component agar versi mobile dan desktop tidak lagi menduplikasi seluruh struktur menu.
- Mempertahankan seluruh permission gate, named route, active route state, `wire:navigate`, serta proses logout.
- Mengubah label **Log Out** menjadi **Keluar** dan fallback role **User** menjadi **Pengguna**.
- Menambahkan shared component untuk sidebar link dan navigation icon dengan stroke yang konsisten.
- Menambahkan skip link **Lewati ke konten utama** dan target `main-content`.
- Menambahkan `aria-current` pada menu aktif, label navigasi, state drawer, state theme, label icon button, decorative icon handling, dan penutupan drawer menggunakan Escape.
- Menambahkan focus trap, scroll lock, penutupan drawer setelah navigasi, serta reset state drawer saat viewport masuk ke desktop.
- Mengoreksi minimum height area konten menjadi tinggi viewport dikurangi topbar 60 px.
- Memperbaiki markup logout yang sebelumnya berupa link di dalam button menjadi satu button yang valid.
- Menjaga shadow hanya pada mobile drawer dan dropdown karena keduanya merupakan floating surface.

#### 7. Detail pekerjaan

File terkait:

- `resources/views/livewire/work-orders/show.blade.php`
- `resources/views/components/modal.blade.php`
- `resources/views/components/tab-button.blade.php`
- `tests/Feature/WorkOrderDetailViewTest.php`

Perubahan yang sudah diterapkan:

- Mengubah header menjadi pola detail page: back navigation, identifier utama, status, SLA, metadata, satu primary action, dan satu secondary action.
- Menghapus animasi pulse pada overdue dan memakai shared SLA badge.
- Mengubah workflow menjadi stepper horizontal compact tanpa outer card, dengan current/past/future state yang jelas.
- Menjaga susunan workflow tetap bergantung pada flag `survey_required` dan mempertahankan action `openStatusModal(...)` pada setiap tahap.
- Menambahkan semantic ordered list dan `aria-current="step"` pada workflow.
- Mengubah navigasi tab menjadi shared tab component yang responsif, dapat discroll, dan memiliki `tablist`, `tab`, `tabpanel`, `aria-selected`, serta relasi `aria-controls`.
- Mengubah informasi pekerjaan dan keuangan menjadi flat definition list dengan divider, bukan nested card.
- Mengubah angka keuangan dan tanggal menjadi tabular numerals; monospace tetap dibatasi pada nomor kontrak, nomor laporan, dan nomor resi.
- Mengubah riwayat status menjadi timeline datar dengan semantic article/time, tanpa mini card per kejadian.
- Mempertahankan SLA dan PIC sebagai context surface independen pada aside karena keduanya memiliki action sendiri.
- Mengurangi warna workflow/PIC ke brand dan neutral family agar warning/danger tetap bermakna.
- Mengubah tab aset dan dokumen menjadi flat table dengan caption, scoped header, row key, empty state, dan text action.
- Mengubah laporan resmi menjadi divided article list dengan financial summary, delivery status, dan hierarchy action yang tenang.
- Mengganti label **Download** menjadi **Unduh**, menambahkan `rel="noopener"`, dan memberi accessible name pada action yang membuka tab baru.
- Memigrasikan tujuh modal—aset, laporan, pengiriman, dokumen, status, PIC, dan SLA—ke shared modal dengan ukuran 448 atau 576 px.
- Menambahkan dialog semantics, labelled title, Escape close, backdrop close, focus management, body scroll lock, responsive field grid, dan loading/disabled submit state.
- Mengubah seluruh field modal ke shared label/input/select/textarea/error components dan sentence case.
- Membandingkan kontrak form dengan versi awal; tidak ada `wire:model` atau `wire:submit` yang hilang dan seluruh action handler tetap tersedia.
- Menambahkan regression test yang memeriksa empat tab dan tujuh entry point modal.

#### 8. Master data dan hak akses

File terkait:

- `resources/views/livewire/master/branches.blade.php`
- `resources/views/livewire/master/debtors.blade.php`
- `resources/views/livewire/master/organizations.blade.php`
- `resources/views/livewire/master/users.blade.php`
- `resources/views/livewire/master/roles-permissions.blade.php`
- `resources/views/components/active-status-badge.blade.php`

Perubahan yang sudah diterapkan:

- Menyamakan lima layar administrasi ke pola `ui-page`, page header, toolbar filter, flat table, pagination, empty state, dan shared modal.
- Menjaga seluruh pencarian, filter, pagination, create, edit, delete, toggle status, serta assignment role/permission yang sudah ada.
- Menambahkan label tersembunyi pada filter, caption tabel, `scope` header, `wire:key`, nama action yang kontekstual, dan loading/disabled state.
- Menggunakan active-status badge untuk membedakan status data master dari workflow pekerjaan.
- Mengubah layout peran dan hak akses menjadi sidebar peran 256 px dan panel permission fleksibel, tanpa card bersarang.
- Mengelompokkan permission dalam semantic `fieldset`, menampilkan state peran aktif melalui `aria-pressed`, dan mempertahankan satu action simpan yang dominan.
- Melokalkan label permission untuk presentation layer tanpa mengganti permission key, nama route, atau role di database.
- Menambahkan regression test khusus untuk Cabang, Debitur, Organisasi, Pengguna, serta Peran & Hak Akses.

#### 9. Audit aktivitas dan impor data

File terkait:

- `resources/views/livewire/audit/activity-log-index.blade.php`
- `resources/views/livewire/imports/data-import.blade.php`
- `resources/views/components/flash-message.blade.php`

Perubahan yang sudah diterapkan:

- Mengubah audit log menjadi toolbar filter dan flat table dengan action badge, detail IP yang jujur, empty state, serta semantic table markup.
- Menghapus fallback IP palsu `127.0.0.1`; data kosong sekarang ditampilkan sebagai em dash.
- Mempertahankan action backup, tetapi tidak menjalankannya selama redesign atau regression test karena bersifat operasional.
- Mengubah impor menjadi alur dua tahap yang jelas: unggah sumber lalu tinjau staging sebelum proses produksi.
- Menambahkan shared field/button/flash, feedback loading, batasan format CSV/TXT, ringkasan batch, semantic table, row key, serta tabular financial value.
- Membedakan item siap diproses, berhasil, dan gagal dengan badge semantic yang benar.
- Menjaga action `downloadTemplate`, `uploadFile`, `processBatch`, dan `clearStaging` beserta seluruh binding yang sudah ada.
- Menambahkan regression test audit dan impor dengan storage palsu untuk mencegah penulisan file/data produksi yang tidak disengaja.

#### 10. Welcome, autentikasi, profil, dan halaman 403

File terkait:

- `resources/views/welcome.blade.php`
- `resources/views/livewire/welcome/navigation.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/livewire/pages/auth/*.blade.php`
- `resources/views/profile.blade.php`
- `resources/views/livewire/profile/*.blade.php`
- `resources/views/errors/403.blade.php`
- `resources/views/components/application-logo.blade.php`
- `resources/views/components/auth-heading.blade.php`

Perubahan yang sudah diterapkan:

- Mengganti landing page bergaya marketing menjadi entry page internal yang ringkas, tanpa hero berlebihan, emoji, feature-card buffet, gradient, atau dekorasi generik.
- Mengganti logo starter Laravel dengan identitas KJPP berbasis SVG yang mengikuti warna teks dan tetap tajam di light/dark mode.
- Menyatukan shell tamu dengan token aplikasi, preferensi theme tersimpan/system, skip link, dan brand heading yang konsisten.
- Menstandardisasi Login, Register, Lupa Password, Reset Password, Konfirmasi Password, dan Verifikasi Email tanpa mengubah kontrak Volt, binding, action, atau redirect.
- Melokalkan copy utama, menambahkan heading/deskripsi yang jelas, validation feedback, serta loading/disabled state pada seluruh submit.
- Mengubah Profil menjadi halaman datar dengan section informasi akun, password, dan danger zone yang terpisah oleh divider.
- Menstandardisasi modal hapus akun dan memperbaiki copy agar tidak menjanjikan cascade deletion yang tidak sesuai constraint data.
- Mengubah halaman 403 menjadi fallback minimal yang hanya menawarkan Dashboard bila user berizin; user lain diarahkan ke Profil agar tidak masuk loop 403.
- Menambahkan regression test untuk CTA welcome, seluruh account state, tiga form profil, modal hapus akun, dan fallback 403.

### Follow-up behavior dan security

Temuan berikut sengaja **tidak** dicampurkan ke commit visual karena membutuhkan keputusan produk, perubahan otorisasi, atau kontrak data:

#### Pekerjaan

- `delivery_note` tersedia di backend tetapi belum memiliki field pada UI.
- `selected_asset_ids` masih disinkronkan otomatis dan belum memiliki selector pada form laporan.
- Permission action masih divalidasi ketika submit; sebagian tombol belum disembunyikan berdasarkan permission.
- Status `BATAL` ditampilkan melalui status badge, tetapi tidak menjadi tahap pada stepper linear.
- Scope pencarian record untuk sebagian action edit/hapus perlu audit authorization dan branch isolation terpisah.

#### Penawaran

- Konversi penawaran ke pekerjaan belum memakai transaction/idempotency guard sehingga retry perlu diamankan dari duplikasi pekerjaan atau status history.
- Field hasil kalkulasi masih berupa public state; backend perlu menghitung ulang nilai turunan sebelum persist.
- Kombinasi kondisi pencarian `OR` dan filter perlu dikelompokkan agar scope filter tidak terlewati.
- Record yang sudah dikonversi masih perlu aturan bisnis yang eksplisit tentang field mana yang boleh diedit.

#### Master data dan akun

- Kolom role pada User dan role Spatie masih menjadi dua sumber kebenaran; opsi role pada layar Pengguna juga masih hard-coded.
- Login belum menolak user dengan status nonaktif.
- Registrasi publik sudah ditutup; akun hanya dibuat dari menu Pengguna oleh pemilik permission `users.manage`.
- Email verification memiliki route/screen, tetapi model User belum mengimplementasikan `MustVerifyEmail`.
- Self-delete perlu keputusan produk, perlindungan last sysadmin, audit trail, dan penanganan foreign key record produksi.
- Delete organisasi/debitur serta perubahan role perlu failure handling yang lebih ramah ketika terhalang relasi data.

#### Impor data

- `currentBatchId` adalah public property sementara staging belum memiliki ownership; isolasi batch per user perlu ditambahkan.
- Refresh kehilangan batch aktif dan dapat meninggalkan staging orphan.
- `clearStaging` belum meminta konfirmasi destructive.
- Pemrosesan belum transactional/idempotent per item; retry dapat meninggalkan partial write atau duplicate status history.
- Parser perlu validasi header dan normalisasi format angka/tanggal Indonesia yang lebih ketat.
- Raw exception pada item gagal sebaiknya diganti pesan aman untuk pengguna dan detailnya dicatat di log.
- `downloadTemplate` perlu explicit authorization yang konsisten dengan action impor lainnya.

#### Keputusan produk

- Registrasi publik ditutup dan pembuatan akun dilakukan melalui menu Pengguna.
- Tentukan apakah email verification benar-benar diwajibkan atau seluruh account state verifikasi dihapus.
- Tentukan apakah self-service account deletion diizinkan pada aplikasi internal.
- Tentukan apakah route `/` tetap menjadi welcome ringkas atau langsung mengarah ke Login.

### Commit Implementasi

Sebanyak **69 file implementasi/test** telah dipecah ke sepuluh commit fungsional:

| Commit | Fungsi |
|---|---|
| `d3ae2fa` | Shared design system dan UI foundation. |
| `7d6b488` | Application shell dan navigasi. |
| `1b91959` | Workflow daftar/detail pekerjaan. |
| `87fa23e` | Dashboard dan Laporan Produksi. |
| `d681845` | Workflow pengelolaan Penawaran. |
| `06e3f48` | Master data referensi. |
| `4649c82` | Pengguna, peran, dan hak akses. |
| `8df3692` | Audit aktivitas. |
| `0d4e523` | Impor data. |
| `ff2dbff` | Welcome, autentikasi, profil, dan halaman 403. |

File backend yang berubah tetap dibatasi pada `Dashboard.php` dan `ProductionReport.php` untuk memindahkan analytics; redesign lain mempertahankan kontrak Livewire existing. Commit masih lokal dan belum di-push.

Regression test baru:

- `WorkOrderDetailViewTest.php` — 1 test / 18 assertion.
- `DashboardAndProductionReportTest.php` — 3 test / 41 assertion.
- `OffersWorkflowTest.php` — 3 test / 75 assertion.
- `MasterBranchesTest.php` — 4 test / 39 assertion.
- `MasterDebtorsTest.php` — 4 test / 33 assertion.
- `MasterOrganizationsTest.php` — 5 test / 45 assertion.
- `MasterUsersTest.php` — 8 test / 66 assertion.
- `MasterRolesPermissionsTest.php` — 5 test / 38 assertion.
- `ActivityLogViewTest.php` — 7 test / 30 assertion.
- `DataImportViewTest.php` — 6 test / 57 assertion.
- `AuthRedesignViewTest.php` — 7 test / 76 assertion.

### Pekerjaan Berikutnya

Urutan lanjutan yang disarankan:

1. Melakukan walkthrough browser pada lebar 360, 768, dan 1280 px untuk light/dark mode, termasuk overflow table, tab, dropdown, dan ketujuh modal pekerjaan.
2. Menguji keyboard-only flow: skip link, sidebar/drawer, theme switcher, dropdown akun, tab, dialog focus trap/return, serta seluruh form utama.
3. Memutuskan kebijakan email verification, self-delete, dan perilaku route `/`; registrasi publik sudah ditutup.
4. Menangani hardening authorization, branch isolation, transaction, idempotency, dan ownership batch sebagai fase backend terpisah.
5. Menambah browser/visual/a11y automation bila diperlukan; test sekarang mengunci render, data, binding, action, dan semantic contract, bukan pixel rendering.
6. Memverifikasi bahwa scaffold lama yang tidak lagi dipakai dapat dihapus: `resources/views/dashboard.blade.php`, `components/nav-link.blade.php`, dan `components/responsive-nav-link.blade.php`.
7. Menyelesaikan formatter repository pada commit terpisah agar perubahan style PHP lama tidak tercampur dengan redesign UI.

### Catatan Validasi

- Status ini disusun dari perbandingan terhadap commit awal `e8e6f48` (`feat: add create offer form and update offers index`).
- Perubahan implementasi tercatat dalam commit `d3ae2fa` sampai `ff2dbff`; dokumentasi dicatat terpisah.
- `composer validate --strict --no-check-publish` berhasil.
- `npm.cmd run build` berhasil menggunakan Vite 8.2.1 dan pipeline Tailwind 3; bundle CSS produksi sekitar 71 kB sebelum gzip.
- Seluruh Blade template berhasil dikompilasi melalui `php artisan view:cache`.
- Seluruh test suite berhasil: **86 test, 604 assertion**.
- Sebelas regression test baru menyumbang 53 test dan 518 assertion untuk kontrak redesign utama.
- Modified PHP scope (`Dashboard.php` dan `ProductionReport.php`) serta seluruh file test baru sudah lulus Pint terarah.
- Repository-wide `pint --test` masih menemukan style issue pada file PHP lama di luar scope visual. Formatter tidak dijalankan massal agar tidak mencampur rewrite yang tidak terkait.
- `git diff --check` tidak menemukan whitespace error setelah dokumentasi final.
- Checklist visual manual lintas viewport dan dark mode belum dinyatakan selesai.

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

Akun dibuat oleh administrator; public registration tidak ditampilkan.

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
