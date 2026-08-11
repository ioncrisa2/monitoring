RENCANA PENGEMBANGAN<br>APLIKASI MONITORING LAPORAN PRODUKSI

Transformasi workflow Excel menjadi aplikasi web responsif

| Basis analisis | LAPORAN PRODUKSI 2026.xlsx dan laporan produksi 2024–2025 |
| --- | --- |
| Fokus | Penawaran → Survey/No Follow-up → Pengerjaan → Cetak → Selesai |
| Arsitektur | Monolith web sederhana, ringan, mudah dirawat |
| Target pengguna | Admin/operator, atasan/supervisor, reviewer/surveyor, admin sistem |
| Tanggal rencana | 11 Agustus 2026 |

Dokumen ini dirancang sebagai blueprint implementasi, bukan sekadar pemindahan tabel Excel ke browser.

# 1. Ringkasan Eksekutif

File produksi 2026 saat ini menggabungkan data penawaran, kontrak, pajak, pelaksana inspeksi, SLA, reviewer, nomor/tanggal laporan, tujuan penilaian, lokasi aset, nilai laporan, tanggal cetak, tanggal kirim, dan nomor resi dalam satu baris kerja. Warna juga dipakai sebagai penanda proses. Pola ini efektif untuk spreadsheet, tetapi akan menjadi rapuh bila langsung dipindahkan 1:1 menjadi satu tabel database.

Desain yang disarankan memisahkan tiga hal yang secara bisnis berbeda: penawaran, pekerjaan/assignment, dan laporan. Status penawaran dan status pengerjaan juga dipisahkan. Dengan begitu, 'tidak ada kelanjutan' tidak tercampur dengan 'sedang survey' atau 'sedang cetak', dan atasan dapat memonitor bottleneck secara akurat.

## Keputusan desain utama

- Nomor penawaran menjadi titik masuk utama. Admin membuat/menemukan penawaran, lalu mengubahnya menjadi pekerjaan saat disetujui.
- Outcome penawaran dipisah dari status pekerjaan: accepted, no follow-up, rejected/cancelled.
- Flag survey_required menentukan apakah pekerjaan memerlukan inspeksi lapangan.
- Setiap perubahan status disimpan sebagai riwayat, bukan hanya menimpa nilai status terakhir.
- Satu pekerjaan dapat memiliki banyak aset dan banyak laporan; satu laporan dapat mencakup satu atau beberapa aset.
- Dashboard atasan menampilkan aging, SLA, pekerjaan terlambat, beban per personel, konversi penawaran, dan pekerjaan yang menunggu tindakan.
## Stack yang direkomendasikan

| Lapisan | Rekomendasi | Alasan |
| --- | --- | --- |
| Backend + Web | Laravel + Livewire | Satu codebase, CRUD cepat, tidak perlu SPA/API terpisah untuk versi awal. |
| UI | Tailwind CSS + Alpine.js seperlunya | Responsif, ringan, mudah membuat tabel desktop dan card mobile. |
| Database | MySQL / MariaDB | Cukup untuk transaksi, filtering, dashboard, dan histori status. |
| Auth & Role | Laravel auth + role/permission | Role-based access tanpa arsitektur kompleks. |
| Queue/Job | Database queue pada awal | Cukup untuk notifikasi, export, import; Redis belum wajib. |
| File | Local/private storage + backup | Simpan dokumen pendukung secara privat; object storage opsional bila skala naik. |

# 2. Temuan dari Struktur Data Saat Ini

Workbook 2026 memiliki 12 sheet bulanan dan 28 kolom utama. Kolom-kolom tersebut dapat dikelompokkan menjadi enam domain data.

| Domain | Kolom Excel yang terkait | Target modul aplikasi |
| --- | --- | --- |
| Identitas pekerjaan | No, Pusat/Cabang, No. Kontrak, Tanggal Kontrak | Penawaran & Pekerjaan |
| Pihak terkait | Nama Debitur, Pemberi Tugas, Pengguna Laporan | Master pihak/organisasi |
| Keuangan | Fee Penawaran, TA, DPP, PPN, PPh | Keuangan penawaran |
| Operasional | Pelaksana Inspeksi, Tgl Survey, Tgl SLA, Nilai Resume, Reviewer | Workflow pekerjaan |
| Laporan | No. Laporan, Tgl Laporan, Tujuan, Lokasi Aset, Nilai Laporan | Laporan & aset |
| Distribusi | Pelaksana di Laporan, Tgl Cetak, Tgl Kirim, No. Resi | Finalisasi & pengiriman |

Laporan produksi historis 2024–2025 juga menunjukkan bahwa satu entri laporan dapat membawa informasi tujuan penilaian, jenis properti/aset, alamat, dan nilai; dalam beberapa kasus satu pekerjaan/laporan mencakup lebih dari satu aset. Karena itu, desain database perlu mendukung relasi 1-ke-banyak, bukan satu baris datar.

## Masalah bila Excel dipindahkan 1:1

- Data debitur/pemberi tugas berulang pada banyak baris sehingga rawan salah ketik dan duplikasi.
- Status proses tidak eksplisit karena sebagian informasi tersirat dari warna atau kolom yang sudah terisi.
- Tidak ada histori yang dapat menunjukkan siapa mengubah status dan kapan.
- Sulit membedakan pekerjaan terlambat dengan pekerjaan yang memang belum aktif.
- Satu pekerjaan yang memiliki banyak aset akan memaksa duplikasi kolom kontrak/klien.
- Monitoring atasan menjadi agregasi manual, bukan indikator real-time.
# 3. Workflow Bisnis yang Disarankan

## 3.1 Pisahkan outcome penawaran dan status pekerjaan

Ini perubahan paling penting. 'Tidak ada kelanjutan' adalah hasil penawaran, sedangkan survey/pengerjaan/cetak/selesai adalah tahapan pekerjaan setelah penawaran diterima. Menggabungkannya dalam satu status akan membuat logika aplikasi cepat berantakan.

| Objek | Status yang disarankan | Catatan |
| --- | --- | --- |
| Penawaran | DRAFT, DIKIRIM, DITERIMA, TIDAK_LANJUT, DITOLAK/BATAL | Menentukan apakah penawaran menjadi pekerjaan. |
| Pekerjaan | PERSIAPAN, SURVEY, PENGERJAAN, REVIEW, CETAK, SELESAI, BATAL | Hanya ada setelah penawaran diterima. |
| Flag Survey | Ya / Tidak | Jika Tidak, pekerjaan dapat langsung ke PENGERJAAN. |

## 3.2 Alur utama

```text
Penawaran dibuat
   │
   ├── TIDAK_LANJUT / DITOLAK ──> Arsip + alasan
   │
   └── DITERIMA
        │
        └── Buat Pekerjaan dari No. Penawaran
             │
             ├── survey_required = YA ──> SURVEY
             │                               │
             └── survey_required = TIDAK     └──> PENGERJAAN
                                                  │
                                                REVIEW
                                                  │
                                                 CETAK
                                                  │
                                                SELESAI
```

## 3.3 Aturan transisi

- Admin tidak boleh menandai SELESAI bila nomor laporan dan tanggal laporan belum terisi.
- Status CETAK membutuhkan minimal laporan berstatus final/review selesai.
- Jika survey_required = Ya, tanggal survey dan pelaksana survey wajib sebelum status dapat melewati SURVEY.
- Setiap perpindahan status otomatis menulis status_history: status lama, status baru, user, waktu, catatan.
- Atasan dapat melakukan override dengan alasan wajib; override tetap masuk audit log.
- Pekerjaan yang melewati SLA ditandai overdue tanpa perlu status baru.
# 4. Role dan Hak Akses

| Role | Akses utama | Batasan |
| --- | --- | --- |
| Admin/Operator | Input penawaran, ubah menjadi pekerjaan, update data operasional, cetak/kirim | Tidak mengubah user/role dan konfigurasi global. |
| Surveyor/Pelaksana | Lihat assignment sendiri, update jadwal/hasil survey | Tidak mengubah fee, pihak terkait, atau status final. |
| Reviewer | Lihat pekerjaan review, isi reviewer/nilai resume/catatan review | Tidak menghapus pekerjaan. |
| Atasan/Supervisor | Dashboard seluruh cabang, assign/reassign, monitor SLA, override status dengan alasan | Fokus kontrol; perubahan penting diaudit. |
| System Admin | User, role, cabang, master data, backup, konfigurasi | Tidak perlu ikut proses operasional harian. |

Untuk MVP, role Surveyor dan Reviewer bisa belum memiliki halaman khusus. Admin tetap dapat mengisi atas nama mereka, sementara struktur databasenya sudah siap bila akses mandiri ditambahkan nanti.

# 5. Desain Database

Jangan membuat satu tabel 'laporan_produksi' dengan 28+ kolom sebagai sumber kebenaran tunggal. Gunakan model terpisah agar proses dapat berkembang tanpa membuat kolom kosong dan duplikasi data.

```text
branches ──< users
   │
   └──< offers >── organizations/debtors
          │ 1
          │ 0..1
          v
      work_orders ──< work_order_assignments >── users
          │  ├──< work_order_assets
          │  ├──< status_histories
          │  ├──< work_order_documents
          │  └──< reports ──< report_assets >── work_order_assets
          │               └──< deliveries
          └── financial fields / SLA / survey flag
```

## 5.1 Tabel inti

| Tabel | Field penting | Fungsi |
| --- | --- | --- |
| branches | id, code, name, active | Pusat/cabang. |
| users | id, branch_id, name, email, password, active | Pengguna aplikasi. |
| organizations | id, name, type, tax_id?, address? | Pemberi tugas/pengguna laporan; mengurangi duplikasi nama. |
| debtors | id, name, identifier?, address? | Debitur/objek pihak yang dinilai. |
| offers | id, offer_no, offer_date, branch_id, debtor_id, client_id, report_user_id, fee, ta, dpp, ppn, pph, outcome, note | Data penawaran dan hasilnya. |
| work_orders | id, offer_id, contract_no, contract_date, survey_required, sla_date, current_status, started_at, completed_at | Unit pekerjaan utama. |
| work_order_assets | id, work_order_id, asset_type, address, city, province, description | Satu pekerjaan dapat memiliki banyak aset. |
| work_order_assignments | work_order_id, user_id, role_type, assigned_at | Pelaksana inspeksi/reviewer dapat lebih dari satu. |
| reports | id, work_order_id, report_no, report_date, purpose, resume_value, report_value, print_date, final_at | Satu pekerjaan dapat menghasilkan >1 laporan. |
| report_assets | report_id, asset_id | Mapping aset yang tercakup pada laporan. |
| deliveries | id, report_id, sent_date, courier, tracking_no, received_date | Pengiriman laporan. |
| status_histories | id, work_order_id, from_status, to_status, changed_by, changed_at, note | Audit workflow dan aging. |
| documents | id, work_order_id/report_id, type, path, uploaded_by, created_at | Lampiran penawaran/survey/laporan. |
| activity_logs | subject_type/id, event, user_id, before_json, after_json, created_at | Audit perubahan data penting. |

## 5.2 Field turunan yang tidak perlu disimpan sebagai input manual

- DPP, PPN, dan PPh sebaiknya dihitung otomatis dari fee dan parameter pajak; simpan snapshot nilainya saat finalisasi bila diperlukan untuk histori.
- Overdue dihitung dari current date > sla_date dan pekerjaan belum SELESAI.
- Aging status dihitung dari waktu status_history terakhir.
- Durasi pengerjaan dihitung dari started_at sampai completed_at.
- Nomor urut internal dapat dibuat oleh sistem, sedangkan nomor kontrak/laporan tetap mengikuti format kantor.
# 6. Modul Aplikasi dan Desain Layar

| Modul | Fungsi utama | Tampilan responsif |
| --- | --- | --- |
| Dashboard | KPI, overdue, status funnel, workload, pekerjaan terbaru | Desktop: cards + tabel; mobile: cards + list ringkas. |
| Penawaran | Input/edit, outcome, fee/pajak, convert to job | Form 2 kolom desktop, 1 kolom mobile. |
| Pekerjaan | Filter status/cabang/personel/SLA, quick update status | Table desktop; card list mobile. |
| Detail Pekerjaan | Timeline status, pihak terkait, survey, aset, laporan, dokumen | Tabs/sections; action bar sticky. |
| Survey | Jadwal, pelaksana, tanggal realisasi, catatan | Calendar/list sederhana. |
| Laporan | Nomor/tanggal laporan, tujuan, nilai, reviewer, aset terkait | Sub-form per laporan. |
| Cetak & Kirim | Tanggal cetak, tanggal kirim, resi | Quick action dari detail. |
| Master Data | Cabang, organisasi, debitur, tujuan, jenis aset, user | CRUD standar. |
| Audit | Riwayat perubahan dan override | Read-only timeline/table. |
| Import/Export | Import Excel historis dan export produksi | Wizard dengan preview error. |

## 6.1 Wireframe dashboard

```text
[ Pekerjaan Aktif ] [ Overdue SLA ] [ Menunggu Survey ] [ Cetak ] [ Selesai Bulan Ini ]

Filter: [Cabang] [Periode] [Status] [PIC] [Cari nomor/debitur]

STATUS PIPELINE                         PEKERJAAN BUTUH TINDAKAN
Penawaran  12                           • 235/...  SLA H-1
Survey      8                           • 229/...  Survey belum selesai
Pengerjaan 17                           • 228/...  Aging 6 hari
Review      6                           • ...
Cetak       4

DAFTAR PEKERJAAN TERBARU / OVERDUE
No Penawaran | Debitur | Cabang | PIC | SLA | Status | Aging | Aksi
```

## 6.2 Prinsip desain responsive

- Desktop menggunakan data table karena volume pekerjaan tinggi; mobile mengubah row menjadi card, bukan memaksa 15 kolom menyempit.
- Filter utama selalu terlihat; filter lanjutan berada dalam drawer/modal di mobile.
- Status menggunakan badge/chip konsisten, tetapi warna bukan satu-satunya indikator; selalu ada teks status.
- Action paling sering: Update Status, Assign PIC, Tambah Laporan, Cetak/Kirim ditempatkan dekat bagian atas detail.
- Timeline status menggantikan ketergantungan pada warna cell Excel.
- Form panjang dibagi section: Identitas, Keuangan, Survey, Aset, Laporan, Distribusi.
# 7. Monitoring untuk Atasan

Dashboard atasan tidak cukup hanya menghitung jumlah per status. Nilai utama aplikasi ini adalah menunjukkan pekerjaan mana yang perlu intervensi.

| Indikator | Rumus/definisi | Aksi yang didukung |
| --- | --- | --- |
| Overdue SLA | sla_date lewat dan belum selesai | Prioritaskan pekerjaan, reassign PIC. |
| Aging status | Hari sejak status terakhir berubah | Deteksi pekerjaan macet. |
| Conversion rate | Penawaran diterima / penawaran selesai diputuskan | Evaluasi efektivitas penawaran. |
| Lead time | Penawaran diterima → selesai | Bandingkan cabang/personel/periode. |
| Survey pending | survey_required=Ya dan survey belum selesai | Kontrol jadwal inspeksi. |
| Review queue | Status REVIEW | Kontrol beban reviewer. |
| Printing queue | Status CETAK / laporan final belum dikirim | Kontrol administrasi akhir. |
| Workload PIC | Jumlah pekerjaan aktif per pelaksana/reviewer | Pemerataan pekerjaan. |
| Completion per month | Pekerjaan selesai per bulan | Laporan produksi manajemen. |
| Fee pipeline | Total fee penawaran accepted/active/completed | Monitoring komersial sederhana. |

# 8. Business Rules dan Endpoint/Route

## 8.1 Aturan data

- offer_no unik per scope yang disepakati (global atau per cabang/tahun).
- contract_no dan report_no dapat nullable pada awal proses tetapi memiliki unique constraint saat terisi, sesuai kebijakan penomoran.
- Pekerjaan tidak dapat dibuat dari penawaran berstatus TIDAK_LANJUT/DITOLAK.
- Satu penawaran default menghasilkan satu work_order, tetapi struktur dapat diperluas jika nanti satu penawaran memuat beberapa assignment.
- Soft delete untuk data operasional; penghapusan permanen hanya oleh system admin.
- Semua perubahan fee, status, nomor laporan, nilai laporan, dan tanggal finalisasi masuk audit log.
## 8.2 Contoh route monolith

```text
GET    /dashboard
GET    /offers                  POST /offers
GET    /offers/{offer}          PATCH /offers/{offer}
POST   /offers/{offer}/convert-to-job
GET    /jobs                    GET /jobs/{job}
PATCH  /jobs/{job}
POST   /jobs/{job}/status
POST   /jobs/{job}/assignments
POST   /jobs/{job}/assets
POST   /jobs/{job}/reports
POST   /reports/{report}/delivery
GET    /reports/production/export
POST   /imports/production
GET    /audit
```

Untuk versi awal, route web Livewire sudah cukup. API JSON baru diperlukan saat ada kebutuhan mobile app, integrasi pihak ketiga, atau frontend terpisah.

# 9. Arsitektur Sistem

```text
Browser Desktop / Tablet / Mobile
             │ HTTPS
             ▼
      Nginx / Web Server
             │
             ▼
 Laravel Monolith + Livewire
 ├─ Auth & Role
 ├─ Workflow Service
 ├─ Dashboard Queries
 ├─ Import/Export
 ├─ Audit Log
 └─ File Service
      │          │
      ▼          ▼
 MySQL/MariaDB  Private File Storage
      │
      └── Scheduled Backup
```

## Mengapa tidak SPA penuh di awal

Kebutuhan utama adalah form, tabel, filter, status workflow, dashboard, dan audit. SPA Vue/React + API terpisah menambah kontrak API, state management, autentikasi lintas layer, dan dua pola debugging tanpa memberi manfaat sebanding pada MVP. Livewire tetap memberi interaksi dinamis yang cukup, namun deployment dan maintenance tetap satu aplikasi.

## Kapan perlu Redis / object storage / API terpisah

- Redis: saat queue, notification, atau traffic mulai cukup tinggi; bukan kebutuhan hari pertama.
- Object storage: saat volume dokumen besar, multi-server, atau backup lokal tidak lagi cukup.
- API terpisah: saat mobile app/desktop app pihak lain perlu mengakses data yang sama.
- Search engine khusus: belum perlu; MySQL full-text/filter biasa cukup untuk tahap awal.
# 10. Rencana Migrasi Data

1. Buat master cabang dan user terlebih dahulu.
1. Import workbook 2026 per sheet menjadi staging table, bukan langsung ke tabel final.
1. Normalisasi nama debitur/pemberi tugas/pengguna laporan; tampilkan preview duplikasi untuk admin.
1. Buat offers dari data kontrak/penawaran yang tersedia; mapping fee dan pajak.
1. Buat work_orders untuk baris yang sudah memasuki proses operasional.
1. Buat assets dan reports dari kolom laporan/lokasi/nilai yang telah terisi.
1. Validasi total record per bulan antara Excel dan aplikasi.
1. Setelah 2026 stabil, migrasikan laporan 2024–2025 sebagai historical read-only bila dibutuhkan.
## Catatan migrasi historis PDF

PDF 2024–2025 lebih cocok diperlakukan sebagai sumber historical reporting. Struktur laporannya menonjolkan nomor laporan, tanggal, debitur, pemberi tugas, pengguna laporan, nomor register, tujuan, jenis aset, alamat, dan nilai. Data tersebut dapat dimigrasikan setelah modul inti stabil; jangan menjadikan ekstraksi PDF sebagai blocker MVP.

# 11. Tahapan Implementasi

| Fase | Cakupan | Output |
| --- | --- | --- |
| Fase 0 – Discovery | Finalisasi status, role, format nomor, aturan pajak, SLA | Data dictionary + workflow disetujui. |
| Fase 1 – Foundation | Auth, role, cabang, master pihak, offers, work_orders | CRUD inti dan konversi penawaran. |
| Fase 2 – Workflow | Survey, assignment, status history, SLA, reviewer | Tracking end-to-end. |
| Fase 3 – Reporting | Reports, assets, cetak/kirim, export produksi | Pengganti utama Excel. |
| Fase 4 – Dashboard | KPI, overdue, aging, workload, filter | Monitoring atasan. |
| Fase 5 – Migration | Import 2026 + validasi; opsional 2024–2025 | Data historis tersedia. |
| Fase 6 – Hardening | Audit, backup, permission, testing, responsive QA | Siap produksi. |

## 11.1 Prioritas MVP

MVP sebaiknya tidak mencoba mengotomasi seluruh administrasi sekaligus. Target pertama adalah mengganti spreadsheet sebagai sumber kebenaran pekerjaan aktif dan memberi atasan visibilitas real-time.

- Wajib: login/role, penawaran, convert-to-job, status workflow, survey flag, PIC, SLA, laporan, cetak/kirim, dashboard, audit status.
- Tahap berikutnya: notifikasi otomatis, template dokumen, integrasi email/WhatsApp, tanda tangan, billing/accounting, mobile offline.
# 12. Kriteria Selesai / Acceptance Criteria

- Admin dapat membuat penawaran dan menemukan data dengan nomor penawaran/debitur.
- Penawaran dapat ditandai tidak lanjut tanpa membuat pekerjaan.
- Penawaran diterima dapat dikonversi menjadi pekerjaan tanpa input ulang data pihak dan fee.
- Pekerjaan dapat memiliki survey_required Ya/Tidak dan mengikuti alur yang sesuai.
- Atasan dapat melihat seluruh pekerjaan aktif, overdue, aging, dan PIC dari dashboard.
- Setiap perubahan status memiliki user dan timestamp.
- Satu pekerjaan dapat menyimpan banyak aset dan minimal satu laporan.
- Laporan menyimpan nomor/tanggal/tujuan/nilai dan data cetak/kirim/resi.
- Aplikasi dapat export laporan produksi per periode ke Excel dengan struktur yang disepakati.
- Tampilan usable pada desktop dan mobile tanpa horizontal scroll wajib untuk flow utama.
- Hak akses mencegah user non-admin sistem mengubah role/master kritis.
- Backup database dan file dapat dijalankan terjadwal serta diuji restore.
# 13. Rekomendasi Akhir

Pilih arsitektur monolith Laravel + Livewire + Tailwind + MySQL. Ini paling proporsional untuk aplikasi internal operasional: cepat dibangun, deployment sederhana, resource server kecil, dan mudah dipelihara. Hindari memulai dengan microservices, SPA terpisah, Redis, atau object storage kecuali ada kebutuhan yang jelas.

Prioritas desain bukan menyalin warna dan kolom Excel, melainkan membuat workflow eksplisit. Penawaran dan pekerjaan harus menjadi dua objek berbeda, status harus memiliki histori, dan aset/laporan harus memiliki relasi sendiri. Dengan fondasi ini, fitur monitoring atasan, SLA, audit, laporan produksi, dan pengembangan berikutnya dapat ditambahkan tanpa merombak struktur inti.

## Keputusan yang perlu dikunci sebelum coding

1. Apakah nomor penawaran berbeda dengan nomor kontrak pada proses bisnis aktual, dan kapan nomor kontrak diterbitkan?
1. Apakah satu penawaran selalu menjadi maksimal satu pekerjaan?
1. Apakah satu pekerjaan dapat menghasilkan beberapa nomor laporan?
1. Apakah SLA dihitung dari tanggal kontrak, tanggal survey, atau tanggal dokumen lengkap?
1. Apakah supervisor harus menyetujui perubahan status tertentu atau cukup monitoring/override?
1. Apakah data nilai laporan bersifat sensitif sehingga aksesnya perlu dibatasi per role/cabang?
1. Format export akhir: mempertahankan 28 kolom 2026, mengikuti format produksi 2024–2025, atau membuat format baru yang lebih ringkas?

— Akhir dokumen —
