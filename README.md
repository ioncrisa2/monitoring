# HJAR Flows

**HJAR Flows** adalah Sistem Operasional Internal yang dirancang khusus untuk Kantor Jasa Penilai Publik (KJPP). Sistem ini membantu memusatkan, menstandarkan, dan mempercepat alur kerja dari tahap penawaran hingga penyelesaian pekerjaan, dengan konsistensi lintas cabang.

## Fitur Utama

- **Manajemen Penawaran (Offer Management)**: Pembuatan, pelacakan, dan persetujuan penawaran. Terintegrasi dengan **Generator Dokumen Penawaran v2** yang mendukung layout dinamis, perhitungan fee otomatis, preflight check, dan persetujuan hierarkis (SoD).
- **Manajemen Pekerjaan & SLA**: Pelacakan Work Orders, pengaturan PIC, aset, dan dokumen pekerjaan dengan pemantauan Service Level Agreement (SLA).
- **Laporan Produksi**: Fitur pelaporan terpusat untuk memantau produktivitas dan status pekerjaan secara real-time.
- **Master Data**: Pengelolaan entitas inti aplikasi seperti Cabang, Pengguna, Role & Hak Akses (Permissions), Klien (Pemberi Tugas), dan Debitur. Termasuk manajemen Master Template Dokumen.
- **Audit Trail (Jejak Audit)**: Pencatatan otomatis setiap aktivitas dan perubahan data penting dalam sistem.
- **Impor Data**: Fasilitas migrasi dan impor data historis.

## Tech Stack

- **Framework:** Laravel 11 (PHP 8.2+)
- **Frontend:** Livewire 3 (Volt) & Tailwind CSS
- **Database:** SQLite / MySQL / PostgreSQL
- **PDF Generation:** DomPDF

## Panduan Instalasi (Development)

1. Clone repositori ini.
2. Salin file environment: `cp .env.example .env`
3. Install dependensi: `composer install` dan `npm install && npm run build`
4. Generate key: `php artisan key:generate`
5. Jalankan migrasi dan seeder: `php artisan migrate:fresh --seed`
6. Jalankan server lokal: `php artisan serve`

## Lisensi

Aplikasi proprietary. Tidak untuk didistribusikan secara publik.
