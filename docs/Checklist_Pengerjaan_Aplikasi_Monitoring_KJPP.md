# Checklist Progress Pengerjaan Aplikasi Monitoring Laporan Produksi KJPP

Dokumen ini digunakan untuk melacak (tracking) status pengerjaan pengembangan **Aplikasi Monitoring Laporan Produksi KJPP** berbasis alur kerja dari `docs/Rencana_Aplikasi_Monitoring_Laporan_Produksi_KJPP.md`.

---

## 📊 Summary Progress Status

- [x] **Fase 0: Persiapan & Discovery** (`100%` - Keputusan bisnis terkunci)
- [x] **Fase 1: Fondasi & Core Architecture** (`100%` - Selesai)
- [x] **Fase 2: Management Penawaran & Pekerjaan (Workflow)** (`100%` - Selesai)
- [x] **Fase 3: Modul Laporan, Aset & Pengiriman** (`100%` - Selesai)
- [x] **Fase 4: Monitoring Atasan & Dashboard KPI** (`100%` - Selesai)
- [x] **Fase 5: Migrasi Data Historis (Excel 2026 & 2024–2025)** (`100%` - Selesai)
- [x] **Fase 6: Audit, Keamanan & Hardening** (`100%` - Selesai)

---

## 📌 Keputusan Bisnis Terkunci (Locked Business Decisions)

Berikut adalah keputusan alur bisnis yang telah dikonfirmasi dan terkunci pada **11 Agustus 2026**:

1. **Nomor Penawaran & Nomor Kontrak**: Nomor Penawaran akan **otomatis menjadi Nomor Kontrak** ketika penawaran disetujui/diterima dan dikonversi menjadi Pekerjaan. Jika penawaran `TIDAK_LANJUT` atau `DITOLAK`, nomor tersebut tetap berupa Nomor Penawaran tanpa membuat Nomor Kontrak.
2. **Cakupan Objek Pekerjaan**: Satu Pekerjaan (dari 1 Nomor Penawaran/Kontrak) dapat menaungi **banyak objek/aset pekerjaan** (`work_order_assets`).
3. **Multi-Laporan per Pekerjaan**: Satu Pekerjaan dapat menghasilkan **lebih dari 1 Nomor Laporan resmi** (`reports`).
4. **Penentuan SLA**: Batas waktu / tanggal SLA ditentukan dan diinput secara manual oleh **Admin**.
5. **Peran & Akses Atasan**: Atasan berperan **murni untuk monitoring** progress & bottleneck via dashboard. Atasan akan melakukan follow-up manual (misal ke Admin) jika terdapat hambatan, tanpa memerlukan workflow approval di sistem.
6. **Akses Data Keuangan**: Data **Fee** dan **Nilai Laporan** dapat diakses oleh **seluruh pengguna internal** (karena merupakan sistem internal perusahaan).
7. **Format Ekspor Excel**: Ekspor data produksi disetujui menggunakan **format baru yang lebih rapi, ringkas, dan terstruktur** (mengelompokkan kolom Identitas, Keuangan, Operasional, Laporan, & Distribusi).

---

## 🚀 Breakdown Checklist Per Fitur & Tahapan

### 0. Fase 0 - Discovery & Business Decision
- [x] **0.1** Locking keputusan bisnis & aturan penomoran (Nomor Penawaran otomatis menjadi Nomor Kontrak jika diterima)
- [x] **0.2** Definisi aturan SLA & penentuan batas waktu (Input manual oleh Admin)
- [x] **0.3** Penentuan format ekspor produksi (Menggunakan usulan format baru yang rapi & ringkas)
- [x] **0.4** Finalisasi Hak Akses (RBAC) per Role (Atasan = Monitoring/Read-only; Akses data internal terbuka)
- [x] **0.5** Setup repository & standar koding (Laravel + Livewire + Tailwind CSS disepakati)

### 1. Fase 1 - Fondasi, Master Data & Autentikasi
- [x] **1.1 Setup Environment & Framework**
  - [x] Inisialisasi proyek Laravel + Livewire + Alpine.js + Tailwind CSS
  - [x] Konfigurasi database SQLite untuk lingkungan development
- [x] **1.2 Autentikasi & Manajeman User (RBAC)**
  - [x] Fitur Login, Logout, Reset Password (Laravel Breeze Livewire Stack)
  - [x] Manajemen Role & Permission (Admin, Surveyor, Reviewer, Supervisor, SysAdmin)
  - [x] Multi-cabang (Branch scope support pada user)
- [x] **1.3 Master Data CRUD**
  - [x] Master Cabang (`branches`) - Full Livewire CRUD
  - [x] Master User (`users`) - Full Livewire CRUD
  - [x] Master Pihak / Organisasi - Pemberi Tugas & Pengguna Laporan (`organizations`) - Full Livewire CRUD
  - [x] Master Debitur / Objek (`debtors`) - Full Livewire CRUD


### 2. Fase 2 - Modul Penawaran & Pekerjaan (Workflow Utama)
- [x] **2.1 Modul Penawaran (Offers)**
  - [x] Form Input Penawaran baru (Nomor Penawaran, Debitur, Klien, Pengguna Laporan)
  - [x] Kalkulasi Otomatis Keuangan Penawaran (Fee, TA, DPP, PPN, PPh)
  - [x] Pengisian Outcome Penawaran (`DRAFT`, `DIKIRIM`, `DITERIMA`, `TIDAK_LANJUT`, `DITOLAK/BATAL`)
  - [x] Riwayat & Catatan outcome penawaran
- [x] **2.2 Conversion Penawaran ke Pekerjaan (Work Orders)**
  - [x] Action **Convert to Job** (Otomatis menyalin data & menjadikan No. Penawaran sebagai No. Kontrak)
  - [x] Auto Input Tanggal Kontrak
  - [x] Setting Flag Survey (`survey_required = Ya / Tidak`)
  - [x] Setting Tanggal SLA Pekerjaan (Input manual oleh Admin)
- [x] **2.3 Tracking Workflow & Penugasan (Assignments)**
  - [x] Penugasan PIC Inspeksi/Surveyor & Reviewer (`work_order_assignments`)
  - [x] Status Pekerjaan Lifecycle (`PERSIAPAN` -> `SURVEY` -> `PENGERJAAN` -> `REVIEW` -> `CETAK` -> `SELESAI`)
  - [x] Validasi transisi status & penugasan PIC
  - [x] Pencatatan Histori Status Otomatis (`status_histories`: user, timestamp, note)
  - [x] Fitur Override Status oleh Atasan / Admin (dengan catatan alasan wajib)
  - [x] Indikator Aging Status & Overdue SLA (real-time calculation)


### 3. Fase 3 - Modul Detail Pekerjaan, Aset, Laporan & Pengiriman
- [x] **3.1 Pengelolaan Multi-Aset (`work_order_assets`)**
  - [x] Tambah / Edit aset dalam 1 Pekerjaan (Dukungan banyak objek per 1 Pekerjaan/Kontrak)
- [x] **3.2 Pengelolaan Laporan (`reports`)**
  - [x] Buat Laporan untuk Pekerjaan (Dukungan >1 Nomor Laporan per Pekerjaan)
  - [x] Mapping Aset ke Laporan (`report_assets`)
  - [x] Input Nilai Resume & Nilai Laporan
- [x] **3.3 Finalisasi, Cetak & Pengiriman (`deliveries`) & Arsip Dokumen (`documents`)**
  - [x] Tanggal Cetak & Status Cetak Laporan
  - [x] Form Pengiriman Laporan (Tanggal Kirim, Kurir, No. Resi, Tanggal Diterima, Nama Penerima)
  - [x] Upload & Simpan Lampiran Dokumen ke Arsip Online (Penawaran, Hasil Survey, Draft Laporan, Scan Final, PDF Historis)


### 4. Fase 4 - Dashboard Monitoring & Executive Analytics
- [x] **4.1 Summary Metric Cards**
  - [x] Counter Pekerjaan Aktif
  - [x] SLA Compliance Rate (%)
  - [x] Counter Overdue SLA
  - [x] Counter Menunggu Survey
  - [x] Counter Dalam Antrean Cetak & Kirim
  - [x] Counter Pekerjaan Selesai Bulan Ini
- [x] **4.2 Actionable Workload & Attention Tables**
  - [x] Daftar Pekerjaan Butuh Tindakan & Bottleneck Ranking (Aging terbanyak)
  - [x] Filter Multi-dimensi (Cabang, Periode, Status, Text Search)
- [x] **4.3 Dashboard Monitoring Atasan**
  - [x] Pipeline Status Funnel (Visualisasi alur pengerjaan)
  - [x] Review & Printing Queue Monitor
  - [x] Rekap Monthly Completion & Fee Pipeline (Fee Penawaran vs Fee WIP vs Fee Selesai)

### 5. Fase 5 - Import / Export Data & Migrasi Historis
- [x] **5.1 Ekspor Data Produksi**
  - [x] Export Laporan Produksi ke Excel (Format ringkas & rapi yang dikelompokkan per 5 kategori data)

- [x] **5.2 Import & Migrasi Workbook 2026**
  - [x] Staging table untuk data Excel 2026 / CSV (`import_stagings`)
  - [x] Fitur preview & normalisasi auto-matching data duplikat (Debitur, Pemberi Tugas, Pengguna Laporan)
  - [x] Converter otomatis Staging ke Tabel Utama (`offers`, `work_orders`, `reports`, `deliveries`)
  - [x] Validasi total record & status hasil import
- [x] **5.3 Migrasi Data Historis 2024–2025**
  - [x] Fitur Impor data laporan historis ke arsip produksi


### 6. Fase 6 - Audit Log, Security, Backup & Responsive QA
- [x] **6.1 Audit Trail & Logging System**
  - [x] Activity Log perubahan data krusial (`activity_logs`: Fee, Penomoran Laporan, Override, Delete, Convert, Backup)
  - [x] Tampilan Timeline Audit & System Audit Trail Page (`/audit-logs`)
- [x] **6.2 Sistem Backup & Resilience**
  - [x] Backup otomatis Database SQLite via Artisan Command (`db:backup-sqlite`) dan 1-Click Button
  - [x] Penyimpanan file cadangan di direktori terproteksi `storage/app/backups`
- [x] **6.3 UI/UX Responsif & Optimization**
  - [x] Optimasi layout responsif desktop & mobile
  - [x] Pass 26 Automated Unit/Feature Tests (`php artisan test`)

