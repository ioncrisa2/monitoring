# Generator Dokumen Penawaran v2

Dokumen ini adalah runbook implementasi aktif untuk katalog template dan arsip PDF penawaran. Dokumen perencanaan lama tetap tersedia di `docs/penawaran-otomatis.md`, tetapi kontrak runtime v2 di bawah ini menjadi rujukan utama.

## Cakupan

Generator hanya menghasilkan penawaran/SPK jasa penilaian tanpa tanda tangan digital, gambar tanda tangan, atau gambar stempel. Laporan penilaian final, pengiriman otomatis, dan arsip scan bertanda tangan berada di luar scope.

Katalog awal berisi tepat tiga draft tersanitasi:

| Kode | Kategori | Tujuan utama |
|---|---|---|
| `property-collateral` | Penjaminan Utang / Properti | Satu atau banyak aset dengan Nilai Pasar |
| `property-auction` | Lelang Properti | Nilai Pasar dan Nilai Likuidasi, exposure/diskon, fee per aset |
| `property-rental` | Nilai Sewa Pasar | Penentuan nilai sewa properti |

Seeder tidak menyimpan PDF sumber atau data pelanggan. Seeder idempotent dan tidak menimpa draft yang sudah diedit atau masuk workflow review.

## Kontrak template v2

- `schema_version = 2`
- `layout_version = offer-a4-v2`
- `header_mode = all_pages`
- tepat 25 klausul, dengan urutan dari `config/offer-documents.php`
- root schema tepat `document`, `defaults`, `clauses`, dan `constraints`
- blok yang diizinkan: `text`, `bullets`, `dynamic`, `asset_list`, `fee_summary`, `fee_table`, `payment_terms`, `requirements`, dan `exposure_table`
- token, kondisi, dan sumber dinamis hanya berasal dari whitelist `OfferTemplateSchemaV2`
- HTML, Blade, PHP, remote asset, dan data URI tidak diterima

Schema `standard-v1` tetap dapat dibaca untuk dokumen historis. UI authoring dan approval normal tidak dapat membuat approval baru untuk schema v1.

## Workflow master

```text
Sysadmin membuat atau menyalin draft
        -> submit
Supervisor independen mereview
        -> approve | reject
Approved master dapat di-retire
```

- Pembuat atau pengaju tidak dapat mereview master yang sama.
- Konten berstatus `submitted`, `approved`, `rejected`, atau `retired` immutable.
- Perubahan dilakukan dengan membuat versi draft baru.
- Checksum SHA-256 dihitung dari payload konten kanonik dan diverifikasi ulang saat submit, review, pemilihan, snapshot, serta finalisasi.
- Profil penerbit v2 hanya dapat disetujui bila letterhead privat lolos verifikasi path relatif, SHA-256, MIME PNG/JPEG, ukuran, dan dimensi aktual.
- Master penandatangan hanya memuat identitas teks, jabatan, izin, dan registrasi.

Permission:

| Permission | Pemilik awal |
|---|---|
| `offers.document-masters.view` | Sysadmin, Supervisor |
| `offers.document-masters.manage` | Sysadmin |
| `offers.document-masters.approve` | Supervisor; Sysadmin tidak memiliki permission approval |

Profil penerbit dan penandatangan dibatasi ke cabang pengguna. Akses lintas cabang hanya tersedia bagi pengguna yang juga memiliki `offers.cross-branch`; template tetap merupakan katalog global.

## Workflow dokumen

```text
Admin memilih template approved
        -> simpan dan preview draft hidup
        -> submit snapshot
Supervisor mengunduh PDF snapshot yang sama
        -> approve | reject
        -> finalize snapshot approved
        -> unduh artifact final privat
```

- Pergantian template menerapkan ulang default template, tetapi mempertahankan penerima, pihak, aset, dokumen, nominal fee, dan catatan internal.
- Submit menjalankan strict preflight, menyimpan snapshot immutable, hash snapshot, dan PDF draft ber-watermark.
- Approval menolak self-approval, snapshot yang bukan lagi review aktif, perubahan draft hidup, checksum rusak, atau artifact review yang berubah.
- Finalisasi bersifat idempotent dan selalu merender snapshot approved; endpoint print-ready hanya mengunduh artifact final yang sudah tersimpan.
- Revisi berikutnya membuat nomor versi baru dan menandai versi final sebelumnya `superseded` tanpa menghapus snapshot atau artifact lama.
- Semua artifact berada pada disk privat `local` dan setiap unduhan memverifikasi ukuran serta SHA-256.

## Strict preflight

Print-ready memerlukan:

- nomor surat teralokasi;
- template, profil penerbit, dan penandatangan approved, efektif, serta lolos checksum;
- schema v2 yang valid dan tepat 25 klausul;
- letterhead resmi tervalidasi untuk layout v2;
- tujuan dan dasar nilai sesuai constraint template;
- penerima, pihak, aset, serta dokumen kepemilikan lengkap;
- fee, pajak, total, terbilang, dan termin 100% konsisten;
- SLA angka/teks dari satu sumber;
- untuk lelang: `per_asset`, satu pemetaan fee per aset, exposure, Nilai Pasar, Nilai Likuidasi, dan diskon yang konsisten.

## Output PDF

- A4 potret;
- letterhead resmi sebagai fixed header di setiap halaman;
- watermark hanya pada draft;
- tanpa footer atau nomor halaman;
- tabel memakai header berulang dan baris yang diusahakan tidak terpotong;
- blok tanda tangan memiliki ruang kosong untuk proses basah;
- renderer tidak mengaktifkan PHP, JavaScript, atau akses remote.

## Aktivasi

Jalankan secara berurutan:

```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=OfferDocumentTemplateSeeder
npm run build
php artisan test
```

Setelah deployment:

1. Sysadmin melengkapi tiga draft template, profil penerbit, masa berlaku, letterhead resmi, dan penandatangan.
2. Supervisor independen menyetujui setiap master.
3. Tim menjalankan golden visual 144 DPI dan UAT cetak A4.
4. Setelah redaksi, aset, font, dan hasil cetak disetujui pemilik proses, set `OFFER_DOCUMENT_FINALIZATION_ENABLED=true` lalu muat ulang cache konfigurasi deployment.

## Batas aktivasi

Tanpa letterhead resmi dan master approved, draft tetap dapat dipreview tetapi strict preflight mencegah finalisasi. Golden visual dan UAT printer merupakan gate operasional; data contoh pelanggan tidak boleh digunakan sebagai fixture repository.
