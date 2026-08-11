# Rencana Penerapan Fitur Penawaran Otomatis

## Status Dokumen

- Tanggal analisis: 12 Agustus 2026.
- Status: vertical slice PDF draft telah diterapkan; finalisasi produksi tetap menunggu keputusan dan aset Fase 0.
- Sumber: tiga PDF penawaran resmi yang diberikan sebagai acuan.
- Cakupan: pembuatan, pratinjau, finalisasi, versioning, penyimpanan, dan pengunduhan dokumen penawaran PDF.
- Prinsip: format visual dan urutan dokumen mengikuti contoh, sedangkan typo, kontradiksi, redaksi legal, tarif pajak, dan penggunaan tanda tangan harus disetujui pemilik proses sebelum masuk template produksi.

### Status implementasi per 12 Agustus 2026

Sudah diterapkan dan dapat diuji:

- Schema additive serta model untuk engagement, banyak pihak/aset/dokumen kepemilikan, fee, termin, requirement, template/profil/penandatangan terversi, nomor atomik, version, dan artifact. Field Penawaran lama tetap dipertahankan.
- Nomor Penawaran dialokasikan server-side secara atomik, global per tahun. Nilai pada form hanya pratinjau; nomor, tanggal, dan cabang terkunci setelah alokasi. Nomor legacy yang valid dapat diadopsi ke ledger tanpa diubah.
- Kalkulator fee/pajak integer Rupiah untuk mode `included`, `excluded`, dan `non_taxable`, pembulatan termin, serta terbilang Bahasa Indonesia.
- Editor dokumen terpisah untuk penerima, referensi, lingkup, banyak pihak/aset, banyak dokumen per aset, fee, pajak, termin, persyaratan, asumsi, dan catatan internal.
- Penyimpanan draft transactional, scope ID nested terhadap Offer/cabang, optimistic `lock_version`, batas jumlah/nilai input, snapshot deterministik, dan preflight mode draft/review/final.
- Preview serta unduh PDF draft A4 melalui Dompdf dengan 25 klausul terurut, watermark `DRAF`, kop teks pada halaman ganjil, escaping output, throttle, audit log, dan policy cabang. Remote asset, PHP, JavaScript, signature image, serta stamp dinonaktifkan.
- Disk privat dan schema version/artifact sudah disiapkan untuk fase arsip/finalisasi, tetapi endpoint saat ini masih merender draft non-persisten.

Keputusan kompatibilitas sementara:

- Aplikasi existing sudah memberikan nomor saat Penawaran disimpan. Slice ini mempertahankan waktu alokasi tersebut, tetapi membuatnya atomik dan immutable. Target desain mengalokasikan nomor saat submit review; waktu final harus diputuskan pada Fase 0 agar draft terbengkalai tidak membakar nomor tanpa kebijakan void/gap.
- Suffix seperti `.A` dapat diadopsi dari nomor legacy dan dapat diformat allocator, tetapi aturan membuat revisi pada base sequence yang sama belum diaktifkan sebelum arti suffix disetujui.
- Template, identitas penerbit, dan kalimat legal fallback selalu ditandai provisional/DRAF. Tidak ada PDF final, signature, stamp, atau pengiriman otomatis pada slice ini.

Belum boleh dianggap siap produksi final:

- Redaksi legal 25 klausul, profile penerbit, letterhead/logo resolusi tinggi, font, penandatangan, signature/stamp, tarif pajak, aturan nomor/suffix/void, dan role finalizer belum disetujui.
- Workflow submit review, approval hash, final artifact immutable, supersede, signed scan, recovery job, dan retensi arsip belum diaktifkan.
- Golden visual yang membuktikan fixture A/C tepat 5 halaman dan B tepat 13 halaman belum tersedia; renderer saat ini adalah spike draft aman, bukan baseline visual final.
- Uji 20 request nomor paralel serta migration portability masih perlu dijalankan pada database produksi target (MySQL/PostgreSQL), bukan hanya SQLite test.

Langkah deploy lokal setelah perubahan ini:

```bash
composer install
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
npm install
npm run build
```

## 1. Hasil yang Ingin Dicapai

Fitur Penawaran saat ini berhenti pada pencatatan data komersial. Target pengembangan berikutnya adalah membuat satu alur lengkap:

```text
Isi data penawaran
        ↓
Validasi kelengkapan dan konsistensi
        ↓
Pratinjau dokumen
        ↓
Buat PDF draft
        ↓
Review dan persetujuan internal
        ↓
Finalisasi PDF yang tidak dapat berubah
        ↓
Unduh/kirim dan catat status penawaran
        ↓
Unggah kembali salinan yang telah disetujui klien
```

Target akhirnya adalah pengguna tidak lagi menyusun surat di Word secara manual. Data hanya dimasukkan satu kali, kemudian sistem menghasilkan dokumen penawaran dengan format KJPP yang konsisten, dapat diaudit, dan tetap sama ketika diunduh kembali di kemudian hari.

## 2. Sumber Acuan yang Dianalisis

PDF contoh tidak disalin ke repository karena memuat data operasional dan tanda tangan. Nama, ukuran, dan hash dicatat agar sumber analisis dapat diverifikasi tanpa menyimpan ulang dokumennya.

| Kode | Dokumen acuan | Ukuran | Halaman | SHA-256 |
|---|---|---:|---:|---|
| A | Penawaran Ttd No. 254 an. PT Surya Sukses Cemerlang.pdf | 419.253 byte | 5 | `CDAA7985B0B15B5B132FC256B584DDB862FBB4D873F722CD943B94D7DDC3917F` |
| B | Penawaran No. 190.A ... (Lelang BNI Manado)-2.pdf | 505.913 byte | 13 | `C1F403C88EEE7D6FD6EC1004270C4CB183BBB7717A04648BB2701FF22742F541` |
| C | Penawaran No. 131 an. CV Sindanglaya (Bank Panin Plg).pdf | 413.444 byte | 5 | `BD8A2B405117806B316819C5DAD51769B7F6D8EAE5B439BA0847CA5E6CFE1E80` |

Kesimpulan kondisi sumber:

- Seluruh dokumen menggunakan A4 potret, sekitar 595 × 842 pt.
- Dokumen A terdiri dari empat halaman teks digital dan satu halaman scan penuh.
- Dokumen B dan C dibuat dari Microsoft Word dan memiliki text layer.
- Tidak ada PDF yang menggunakan tanda tangan digital kriptografis.
- Tanda tangan dan stempel yang terlihat merupakan gambar raster atau hasil scan yang sudah diratakan ke PDF.
- Seluruh contoh memiliki beberapa typo, inkonsistensi ejaan, dan redaksi yang perlu review.
- Tidak ada footer, nomor halaman, maupun watermark pada sumber.

## 3. Anatomi Dokumen yang Sama pada Ketiga Contoh

Ketiga dokumen memiliki kerangka utama yang sama:

1. Kop/letterhead KJPP.
2. Nomor surat di kiri dan kota/tanggal penerbitan di kanan.
3. Blok penerima.
4. Perihal yang dipusatkan dan digarisbawahi.
5. Paragraf pembuka yang merujuk permintaan pemberi tugas.
6. Dua puluh lima klausul penugasan.
7. Blok penutup.
8. Tanda tangan penerbit dan ruang persetujuan klien dalam dua kolom.

Urutan 25 klausul yang harus menjadi struktur dasar template:

1. Status Penilai.
2. Pemberi Tugas.
3. Pengguna Laporan.
4. Objek Penilaian.
5. Bentuk Kepemilikan.
6. Jenis Mata Uang yang Digunakan.
7. Maksud dan Tujuan Penilaian.
8. Dasar Nilai.
9. Tanggal Penilaian.
10. Tingkat Kedalaman Investigasi.
11. Sifat dan Sumber Informasi yang Dapat Diandalkan.
12. Asumsi dan Asumsi Khusus.
13. Persyaratan atas Persetujuan untuk Publikasi.
14. Standar Penilaian.
15. Laporan Penilaian.
16. Batasan atau Pengecualian Tanggung Jawab kepada Pihak Selain Pemberi Tugas.
17. Pernyataan Tertulis Pemberi Tugas tentang Kebenaran Informasi.
18. Biaya Jasa Penilaian.
19. Permintaan Data Awal.
20. Kerangka Waktu Pelaksanaan.
21. Prosedur Pelaksanaan Penugasan.
22. Pembatalan Penugasan.
23. Kerahasiaan Informasi.
24. Penutup.
25. Lain-lain.

Judul klausul dan urutannya stabil, tetapi isi beberapa klausul berubah berdasarkan tujuan penilaian, jumlah objek, dasar nilai, bentuk laporan, pajak, termin pembayaran, dan jenis klien.

### 3.1 Pemetaan Klausul ke Sumber Data

| No. | Klausul | Sifat konten | Sumber data utama |
|---:|---|---|---|
| 1 | Status Penilai | Template + profil penerbit/penandatangan | Template version, issuer profile, signer profile. |
| 2 | Pemberi Tugas | Dinamis | Organization snapshot. |
| 3 | Pengguna Laporan | Dinamis | Report-user Organization snapshot; dapat sama dengan pemberi tugas. |
| 4 | Objek Penilaian | Repeatable dan dinamis | OfferSubject dan OfferAsset. |
| 5 | Bentuk Kepemilikan | Dinamis | OfferEngagement dan data aset. |
| 6 | Mata Uang | Dinamis dengan default | OfferEngagement, default IDR. |
| 7 | Maksud dan Tujuan | Dinamis/preset | Purpose yang dipilih. |
| 8 | Dasar Nilai | Template kondisional | Valuation basis; dapat menambah Nilai Likuidasi, Waktu Ekspos, dan tabel diskon. |
| 9 | Tanggal Penilaian | Template + rule dinamis | Valuation date rule. |
| 10 | Kedalaman Investigasi | Template/preset | Investigation level dan override approved. |
| 11 | Sifat dan Sumber Informasi | Template + data dinamis | Template version dan requirement/source list. |
| 12 | Asumsi dan Asumsi Khusus | Template + override | Template version dan special assumptions. |
| 13 | Persetujuan Publikasi | Template legal | Template version. |
| 14 | Standar Penilaian | Template efektif bertanggal | Template version/standard edition snapshot. |
| 15 | Laporan Penilaian | Dinamis | Format, bahasa, jumlah salinan, dan SLA. |
| 16 | Batas Tanggung Jawab | Template legal | Template version. |
| 17 | Pernyataan Pemberi Tugas | Template legal | Template version. |
| 18 | Biaya Jasa | Dinamis/kondisional | Fee items, tax inclusion, rate snapshot, cost inclusions, dan payment terms. |
| 19 | Permintaan Data Awal | Repeatable | OfferRequirement. |
| 20 | Kerangka Waktu | Dinamis | Completion days dan day type; terbilang dibuat sistem. |
| 21 | Prosedur Pelaksanaan | Template dengan preset | Template version; enam tahap dasar pada seluruh contoh. |
| 22 | Pembatalan | Template legal | Template version. |
| 23 | Kerahasiaan | Template legal | Template version. |
| 24 | Penutup | Template + kontak | Issuer/signatory contact dan template version. |
| 25 | Lain-lain | Template legal | Template version dan override approved. |

Blok tanda tangan berada setelah klausul 25 dan mengambil data dari issuer/signatory snapshot serta placeholder persetujuan klien.

### 3.2 Ledger Sumber per Klausul

| Klausul | Contoh A | Contoh B | Contoh C | Variasi yang harus dimodelkan |
|---|---|---|---|---|
| 1–3 | Hal. 1 | Hal. 1 | Hal. 1 | Profil penilai, pemberi tugas, dan pengguna laporan. |
| 4 | Hal. 1 | Hal. 1–6 | Hal. 1 | Satu objek vs 34 pihak/aset; nested repeatable content. |
| 5–7 | Hal. 2 | Hal. 6 | Hal. 2 | Kepemilikan, mata uang, dan purpose. |
| 8 | Hal. 2 | Hal. 6–8 | Hal. 2 | BNI menambah Nilai Likuidasi, Waktu Ekspos, dan tabel koreksi/diskon. |
| 9–10 | Hal. 2 | Hal. 8–9 | Hal. 2 | Aturan tanggal dan investigasi dapat memanjang. |
| 11–17 | Hal. 3 | Hal. 9–10 | Hal. 3 | Legal copy relatif tetap, tetapi standard edition dan output berubah. |
| 18 | Hal. 4 | Hal. 11 | Hal. 4 | Paragraf fee excluded + termin, tabel fee bulk included, atau paragraf fee included. |
| 19 | Hal. 4 | Hal. 11 | Hal. 4 | Checklist dokumen berbeda menurut objek/klien. |
| 20–23 | Hal. 4–5 | Hal. 12 | Hal. 4 | SLA, prosedur, pembatalan, dan kerahasiaan. |
| 24–25 | Hal. 5 | Hal. 12–13 | Hal. 5 | Kontak penutup dan blok tanda tangan/persetujuan. |

Sebelum template pertama di-approve, buat ledger editorial lengkap untuk setiap klausul dengan kolom berikut:

```text
clause_key
source_a_text_hash / source_b_text_hash / source_c_text_hash
approved_legal_text
fixed | variable | conditional
allowed_tokens
condition_schema
source_page_reference
editorial_decision
approved_by / approved_at
```

Teks legal penuh tidak ditempel ke dokumen perencanaan ini karena sumber masih mengandung typo, kontradiksi, dan data operasional. Fase 0 wajib menghasilkan seed/fixture tersanitasi berisi redaksi yang sudah disetujui; fixture tersebut menjadi sumber template dan golden baseline, bukan hasil copy-paste/OCR mentah.

## 4. Perbandingan Varian Dokumen

| Aspek | Contoh A — Surya | Contoh B — BNI | Contoh C — Panin | Kebutuhan sistem |
|---|---|---|---|---|
| Skala | Penawaran sederhana | Penawaran massal | Penawaran sederhana | Mendukung satu maupun banyak pihak/aset. |
| Jumlah halaman | 5 | 13 | 5 | Pagination harus dinamis dan deterministik. |
| Objek | Satu kelompok objek | 34 pihak dengan aset masing-masing | Satu kelompok objek | Relasi nested `pihak → aset`, bukan satu debtor saja. |
| Dasar nilai | Dasar nilai standar | Nilai Pasar, Nilai Likuidasi, dan Waktu Ekspos | Nilai Pasar | Klausul kondisional berdasarkan dasar nilai. |
| Presentasi fee | Paragraf | Tabel 34 baris dan total | Paragraf | Mode fee ringkas dan tabel per aset. |
| Pajak | Belum termasuk PPN 11% | Termasuk PPN 11%, transportasi, dan akomodasi | Termasuk PPN 11% | Mode pajak/inclusion harus eksplisit dan tersnapshot. |
| Termin | 50% + 50% | Tidak dirinci | Tidak dirinci | Termin bersifat opsional dan repeatable. |
| SLA | Ringkas | 30 hari, tetapi sumber memiliki kontradiksi angka/terbilang | Ringkas | Satu sumber angka; teks dihasilkan otomatis. |
| Tabel khusus | Tidak ada | Tabel koreksi/diskon dan tabel fee | Tidak ada | Komponen tabel kondisional dan page-break policy. |
| Tanda tangan | Halaman akhir berupa scan | Gambar tanda tangan/stempel | Gambar tanda tangan/stempel | Bedakan signature image, scan fisik, dan digital signature. |

Temuan penting dari contoh massal:

- Deskripsi objek dan tabel fee mengulang data sertifikat. Pada sumber ditemukan beberapa ketidaksesuaian antarkedua bagian.
- Salah satu nomor urut aset pada sumber tidak mengikuti urutan pihaknya.
- Durasi tertulis dalam format angka dan terbilang yang saling bertentangan.
- Generator wajib memakai satu data relasional untuk semua pengulangan agar mismatch seperti ini tidak dapat terjadi.

## 5. Spesifikasi Fidelity Dokumen

### 5.1 Format halaman

- Ukuran: A4 potret, 210 × 297 mm.
- Margin utama: sekitar 25–27 mm di kiri dan kanan.
- Body: sekitar 11 pt dengan leading sekitar 14–17 pt.
- Isi paragraf mayoritas rata kiri-kanan.
- Warna utama hitam di atas latar putih.
- Tidak ada card, gradient, footer, maupun elemen dekoratif aplikasi.
- Tidak ada nomor halaman pada versi yang mengikuti sumber.

### 5.2 Kop surat

- Letterhead merupakan aset resmi beresolusi tinggi, bukan hasil crop dari PDF contoh.
- Ketiga contoh memperlihatkan kop pada halaman ganjil 1, 3, 5, dan seterusnya; halaman genap tidak memakai kop penuh.
- Perilaku ini dijadikan konfigurasi `header_mode = odd_pages` untuk template awal.
- Pemilik proses harus memutuskan apakah produksi benar-benar mempertahankan pola halaman ganjil atau menampilkan kop pada setiap halaman.
- Identitas cabang, alamat, izin, dan kontak tidak boleh ditulis langsung di Blade.

### 5.3 Grid klausul

Setiap klausul mengikuti empat kolom semu tanpa border:

```text
No. | Judul klausul | : | Isi klausul
```

Ketentuan layout:

- Kolom nomor sekitar 8–10 mm.
- Kolom judul sekitar 40–48 mm.
- Kolom titik dua sekitar 4–6 mm.
- Kolom isi memakai sisa lebar halaman.
- Judul klausul menggunakan bold dan dapat membungkus.
- Bullet menggunakan hanging indent.
- Judul tidak boleh tertinggal sendirian di akhir halaman.
- Baris tabel fee tidak boleh terpotong di tengah halaman.

### 5.4 Tipografi

- Sumber dominan memakai Calibri 11 pt dengan sebagian Arial dan Times New Roman.
- Font PDF harus di-embed agar hasil di server dan komputer pengguna identik.
- Penggunaan Arial/Calibri memerlukan aset font yang lisensinya sah untuk deployment.
- Bila aset berlisensi tidak tersedia, gunakan font metric-compatible yang disetujui setelah visual comparison; penggantian tidak boleh dilakukan diam-diam.
- Font UI aplikasi seperti Figtree tidak otomatis digunakan pada PDF resmi.

### 5.5 Pagination

- Penawaran sederhana diharapkan menghasilkan sekitar lima halaman.
- Penawaran massal dapat berkembang menjadi 13 halaman atau lebih berdasarkan jumlah pihak dan objek.
- Page break tidak boleh di-hardcode berdasarkan nomor halaman saja.
- Renderer harus menghasilkan halaman yang sama untuk snapshot data dan versi template yang sama.
- Tabel panjang harus memiliki aturan split yang eksplisit; pengulangan header tabel menjadi opsi template.
- Visual preflight wajib mendeteksi overflow, baris terpotong, orphan heading, dan blok tanda tangan yang terdorong keluar halaman.

### 5.6 Tanda tangan

- PDF draft tidak memakai tanda tangan/stempel atau menggunakan placeholder yang jelas.
- Gambar tanda tangan KJPP hanya boleh ditempelkan oleh pengguna dengan permission khusus.
- Final dengan signature image bukan tanda tangan digital kriptografis.
- Salinan yang ditandatangani basah oleh klien disimpan sebagai jenis dokumen terpisah.
- Aset signature/stamp tidak boleh berada pada public disk atau URL publik.

## 6. Audit Fitur Penawaran Saat Ini

Data yang sudah tersedia:

- Nomor urut dan nomor penawaran otomatis.
- Tanggal penawaran.
- Cabang penerbit.
- Satu debitur.
- Pemberi tugas/klien.
- Pengguna laporan opsional.
- Fee, TA, DPP, PPN, dan PPh.
- Outcome penawaran.
- Catatan internal.
- Konversi penawaran menjadi pekerjaan.

Kemampuan yang belum tersedia:

- Dependency pembuat PDF.
- Template surat dan versi redaksi legal.
- Profil letterhead per cabang.
- Profil penandatangan beserta izin/registrasi.
- Penerima/PIC surat dan referensi surat permintaan.
- Banyak pihak/debitur dalam satu penawaran.
- Banyak aset per pihak.
- Dokumen kepemilikan dan luas aset.
- Tujuan, dasar nilai, tanggal penilaian, mata uang, bentuk laporan, jumlah salinan, dan SLA dokumen.
- Mode fee ringkas vs fee per aset.
- Status termasuk/belum termasuk pajak dan komponen biaya lain.
- Termin pembayaran repeatable.
- Checklist permintaan data awal.
- Pratinjau, finalisasi, revisioning, hash, dan arsip PDF.
- Permission granular untuk review, finalisasi, signature, dan download.
- Relasi dokumen langsung ke Offer; tabel `documents` saat ini hanya menerima `work_order_id` atau `report_id`.

### 6.1 Gap nomor surat

Format sekarang:

```text
{sequence}/S.Kontrak/KJPP-HJA'R/{branch_number_code}/{bulan_romawi}/{tahun}
```

Contoh sumber menunjukkan kebutuhan tambahan:

- suffix nomor seperti `190.A`;
- segmen kode yang harus dapat dikonfigurasi;
- snapshot format saat dokumen diterbitkan;
- alokasi nomor secara atomik saat beberapa pengguna membuat penawaran bersamaan.

`nextSequence()` saat ini membaca nilai maksimum lalu menambah satu. Untuk penerbitan dokumen resmi, nomor harus dialokasikan dalam transaction/lock dan dilindungi unique constraint berdasarkan tahun, nomor, serta suffix.

Baseline yang direkomendasikan:

- Scope nomor tetap global per tahun seperti perilaku `OfferNumberService` sekarang; cabang hanya menjadi segmen nomor. Perubahan menjadi sequence per cabang harus diputuskan sebelum migration.
- Tambahkan `offer_number_counters` dengan key scope/tahun dan counter terakhir.
- Tambahkan ledger immutable `offer_number_allocations`: `offer_id`, `sequence_year`, `sequence_no`, `number_suffix` default string kosong, `branch_id`, `scope_key`, `format_snapshot`, `full_number`, `status`, actor/timestamp alokasi, serta actor/timestamp/alasan void.
- Offer hanya menyimpan `current_number_allocation_id`; `offer_no`, `sequence_no`, dan `sequence_year` dapat dipertahankan sebagai denormalized compatibility fields yang disinkronkan service, bukan sumber riwayat.
- Alokasi dilakukan dengan row lock/transaction ketika penawaran pertama kali diajukan ke review, bukan saat user baru membuka form draft.
- Sebelum alokasi, UI hanya menampilkan preview nomor yang jelas berstatus belum resmi.
- Sebelum finalisasi, perubahan tahun/cabang/suffix tidak mengedit allocation. Sistem melakukan void terhadap allocation lama lalu membuat allocation baru dengan alasan audit dan memindahkan pointer aktif.
- Setelah dokumen `finalized`/`sent` atau Offer sudah dikonversi menjadi WorkOrder, nomor terkunci dan tidak dapat di-void/dialokasikan ulang. Revisi mempertahankan nomor yang sama kecuali kebijakan suffix resmi kelak menyatakan lain.
- Gap nomor dipertahankan sebagai record void; nomor tidak didaur ulang.
- Suffix seperti `.A` diperlakukan sebagai komponen eksplisit, bukan otomatis dianggap nomor revisi, sampai owner menetapkan aturannya.

Unique constraint baseline:

```text
(scope_key, sequence_year, sequence_no, number_suffix)
```

`full_number` juga unique sebagai pertahanan kedua. Allocation berstatus `allocated` atau `void`; row tidak pernah dihapus. Perubahan status/pointer terjadi dalam transaction yang sama.

Concurrency test wajib menjalankan beberapa alokasi paralel dan membuktikan seluruh nomor unik. Strategi lock harus diverifikasi pada SQLite yang saat ini digunakan dan pada database produksi bila nantinya berbeda.

### 6.2 Gap perhitungan biaya

Perhitungan aplikasi sekarang memakai:

```text
DPP = fee - TA
PPN = DPP × 11%
PPh = DPP × 2%
```

PDF sumber menggunakan arti berbeda:

- ada fee yang sudah termasuk PPN;
- ada fee yang belum termasuk PPN;
- ada fee yang termasuk transportasi/akomodasi;
- PPh dan DPP tidak selalu dicetak;
- ada fee tunggal dan fee per aset.

Karena itu nilai internal aplikasi tidak boleh langsung disalin ke surat. Model komersial dokumen perlu mendefinisikan `tax_inclusion`, rate snapshot, komponen yang termasuk, subtotal, pajak, grand total, dan kalimat yang ditampilkan.

### 6.3 Source of Truth dan Pembulatan

Baseline v1 untuk IDR:

- Nilai uang baru disimpan sebagai integer Rupiah, bukan `float`.
- Tarif disimpan sebagai basis point; 11% disimpan sebagai `1100`.
- `line_total = round_half_up(quantity × unit_amount)`.
- `quoted_amount = sum(line_total)`.
- Mode `excluded`: `tax_base = quoted_amount`, `ppn = round_half_up(tax_base × rate_bps / 10000)`, `document_payable_total = tax_base + ppn`.
- Mode `included`: `document_payable_total = quoted_amount`, `tax_base = round_half_up(document_payable_total × 10000 / (10000 + rate_bps))`, `ppn = document_payable_total - tax_base`.
- Mode `non_taxable`: `tax_base = document_payable_total = quoted_amount` dan `ppn = 0`.
- PPh diperlakukan sebagai withholding/informasi terpisah dan tidak mengurangi nilai surat kecuali owner menyetujui rule/template khusus.
- TA, transportasi, dan akomodasi tidak boleh mengubah tax base secara implisit. Komponen tersebut menjadi fee/cost item atau inclusion flag yang definisinya disetujui.
- Termin dihitung dari `document_payable_total`; termin terakhir menerima residual pembulatan agar total nominal tepat sama.
- Terbilang dibuat dari nilai yang benar-benar dicetak sebagai total penawaran.

`line_total`, tax base, pajak, nominal termin, total, dan terbilang adalah field turunan. Nilai tersebut tidak menjadi mass-assignable input dan selalu dihitung ulang server-side sebelum snapshot/finalisasi. Bila owner membutuhkan pecahan Rupiah atau mata uang lain, precision/currency minor-unit rule harus ditambahkan sebelum scope diperluas.

## 7. Arsitektur Domain yang Disarankan

```text
Offer
├── OfferEngagement             data surat dan penugasan
├── OfferSubject[]              pihak/debitur dalam penawaran
│   └── OfferAsset[]            objek milik pihak tersebut
│       └── OfferAssetDocument[] sertifikat/dokumen kepemilikan
├── OfferFeeItem[]              fee ringkas atau per aset
├── OfferPaymentTerm[]          termin pembayaran
├── OfferRequirement[]          daftar data awal
└── OfferDocumentVersion[]      immutable snapshot + approval
    ├── OfferDocumentArtifact[] draft/final/signed scan
    ├── OfferTemplateVersion
    ├── IssuerProfileVersion
    └── DocumentSignerVersion
```

### 7.1 `offer_engagements`

Relasi one-to-one dengan `offers`. Field minimal:

| Field | Kegunaan |
|---|---|
| `offer_id` | Relasi unik ke penawaran. |
| `workflow_state` | Lifecycle dokumen aktif, terpisah dari outcome komersial. |
| `current_review_version_id` | Versi immutable yang sedang direview. |
| `current_final_version_id` | Versi final aktif; versi lama tetap aktif selama revisi belum final. |
| `state_changed_by` / `state_changed_at` | Actor dan waktu transisi terakhir. |
| `lock_version` | Optimistic lock untuk mencegah dua aksi workflow saling menimpa. |
| `template_version_id` | Versi template legal yang dipilih. |
| `issuer_profile_version_id` | Snapshot sumber letterhead/cabang. |
| `signer_version_id` | Penandatangan yang dipilih. |
| `issue_city` | Kota pada tanggal surat. |
| `recipient_attention` | Nama/divisi penerima. |
| `recipient_organization` | Nama organisasi pada surat. |
| `recipient_address` | Alamat snapshot. |
| `recipient_city` | Kota/provinsi tujuan. |
| `subject` | Perihal surat. |
| `request_reference_type` | `letter`, `email`, `verbal`, `other`, atau `none`. |
| `request_reference_no` | Nomor surat/email permintaan. |
| `request_reference_date` | Tanggal referensi. |
| `opening_context` | Konteks pembuka terstruktur. |
| `ownership_form` | Bentuk kepemilikan. |
| `currency` | Default `IDR`. |
| `purpose` | Tujuan penilaian. |
| `valuation_basis` | Nilai Pasar/Likuidasi/dll. |
| `valuation_date_rule` | Aturan tanggal penilaian. |
| `investigation_level` | Tingkat kedalaman investigasi. |
| `report_format` | Ringkas/lengkap/digital/cetak. |
| `report_language` | Bahasa laporan. |
| `report_copies` | Jumlah eksemplar. |
| `completion_days` | Angka SLA tunggal. |
| `completion_day_type` | Hari kerja atau kalender. |
| `tax_inclusion` | `included`, `excluded`, atau `non_taxable`. |
| `ppn_rate` | Snapshot tarif PPN. |
| `pph_rate` | Snapshot tarif PPh bila relevan. |
| `cost_inclusions` | Transportasi, akomodasi, dan komponen lain. |
| `special_assumptions` | Override yang disetujui. |
| `internal_note` | Tidak pernah dicetak. |

Long-form legal text tidak disimpan sebagai field bebas di tabel ini. Teks berasal dari template version dan hanya override terstruktur yang dapat dimasukkan pengguna.

### 7.2 `offer_subjects`

Mendukung contoh massal yang memiliki banyak debitur/pihak:

- `offer_id`.
- `debtor_id` nullable untuk relasi master.
- `name_snapshot`.
- `identifier_snapshot`.
- `address_snapshot`.
- `is_primary`.
- `sort_order`.

`offers.debtor_id` dipertahankan sementara sebagai primary subject untuk kompatibilitas daftar dan data lama. Setelah migrasi stabil, aplikasi membaca koleksi `offer_subjects` sebagai sumber utama.

Tambahkan unique `(offer_id, sort_order)`. Invariant tepat satu primary subject dijaga dengan slot/index database yang kompatibel dengan target DB serta validator domain; subject primary harus sama dengan `offers.debtor_id` selama kolom legacy masih dipertahankan.

### 7.3 `offer_assets`

- `offer_subject_id`.
- `asset_type`.
- `description`.
- `address`, `city`, dan `province`.
- `land_area_m2`.
- `building_area_m2`.
- `inspection_note`.
- `sort_order`.

Nomor aset selalu dihasilkan dari `sort_order`; pengguna tidak mengetik nomor list secara manual.

Tambahkan unique `(offer_subject_id, sort_order)` agar dua aset dalam subject yang sama tidak memiliki urutan identik.

#### 7.3.1 `offer_asset_documents`

Satu aset dapat memiliki lebih dari satu sertifikat atau bukti kepemilikan. Jangan membatasi aset pada satu pasangan type/number.

- `offer_asset_id`.
- `document_type`.
- `document_no`.
- `issued_at` nullable.
- `issuer` nullable.
- `is_primary`.
- `sort_order`.
- `note` nullable.

Unique constraint minimal adalah `(offer_asset_id, document_type, document_no)`. Hanya satu dokumen yang boleh menjadi primary per aset; tabel fee memilih record yang sama atau menampilkan seluruh dokumen sesuai preset template.

### 7.4 `offer_fee_items`

Mendukung fee tunggal maupun tabel fee per aset:

- `offer_id`.
- `offer_subject_id` nullable.
- `offer_asset_id` nullable.
- `label`.
- `quantity`.
- `unit_amount`.
- `sort_order`.

`line_total`, total, pajak, dan terbilang dihitung server-side dari fee item, bukan disimpan sebagai input atau dipercaya dari public Livewire property. Nilai hasil hitung hanya masuk immutable snapshot/artifact metadata.

`offer_subject_id`/`offer_asset_id` harus berasal dari Offer yang sama. Service snapshot memvalidasi ownership lintas relasi; database/service juga menjamin `sort_order` unik dalam parent. Jika implementasi dapat menurunkan Offer melalui relasi aset, FK `offer_id` yang redundan sebaiknya dihilangkan untuk mengurangi peluang mismatch.

### 7.5 `offer_payment_terms`

- `offer_id`.
- `sequence`.
- `percentage`.
- `trigger_text`.
- `due_days` nullable.

Jika termin digunakan, total persentase harus tepat 100%. Nominal termin dihitung saat snapshot; residual pembulatan ditempatkan pada termin terakhir dan tidak menjadi input mutable.

### 7.6 `offer_requirements`

- `offer_id`.
- `requirement_code` nullable untuk item master.
- `description_snapshot`.
- `emphasis_style` bila template memerlukan bold/italic/underline.
- `sort_order`.

Daftar dapat dimulai dari preset dokumen tanah, izin bangunan, PBB, NPWP, dan identitas pihak, lalu diedit sesuai penugasan.

### 7.7 Profil penerbit dan penandatangan

Gunakan dua entitas terversi:

`issuer_profile_versions`:

- cabang;
- nama legal KJPP;
- nomor izin;
- label kantor pusat/cabang;
- alamat dan kontak;
- kota penerbit;
- aset letterhead;
- hash dan immutable asset version;
- periode efektif;
- checksum;
- approver.

`document_signer_versions`:

- cabang;
- nama dan gelar;
- jabatan;
- nomor izin dan registrasi;
- kontak;
- private path signature/stamp;
- hash dan immutable asset version;
- periode efektif;
- status aktif;
- approver.

Perubahan profil tidak boleh mengubah PDF final yang pernah diterbitkan.

Letterhead/signature/stamp disimpan content-addressed berdasarkan SHA-256. Upload baru selalu membuat asset version baru; file yang sudah direferensikan version/snapshot tidak boleh ditimpa atau dihapus. Finalizer menyalin bytes aset yang disetujui ke render bundle sementara dan menyimpan hash setiap aset dalam snapshot sehingga approval tetap dapat direproduksi walaupun profil aktif berubah.

### 7.8 Template dan versi legal

`offer_templates`:

- kode dan nama template;
- tipe/purpose;
- status aktif;
- default atau bukan.

`offer_template_versions`:

- nomor versi;
- schema 25 klausul dalam JSON terstruktur;
- condition/preset untuk tujuan dan dasar nilai;
- versi layout print;
- mode header;
- tanggal efektif;
- status `draft`, `approved`, atau `retired`;
- approver dan waktu approval;
- checksum konten.

Jangan menyimpan Blade/PHP yang dapat dieksekusi di database. Database hanya menyimpan data teks terstruktur dan kondisi yang telah divalidasi; layout tetap berada di source code.

Kontrak condition engine v1:

- Setiap payload memiliki `schema_version`.
- Klausul diidentifikasi oleh `clause_key` stabil, bukan index array.
- Field yang dapat dibaca condition berasal dari allowlist snapshot, misalnya `purpose`, `valuation_basis`, `subject_count`, `asset_count`, `tax_inclusion`, dan `has_payment_terms`.
- Operator yang diizinkan hanya `equals`, `in`, `contains`, `count_gte`, `and`, `or`, dan `not` dengan tipe operand yang tervalidasi.
- Tidak ada `eval`, expression PHP/JavaScript, raw SQL, atau pemanggilan method.
- Unknown field/operator membuat approval template gagal; renderer tidak melakukan fallback diam-diam.
- Migration schema menyediakan transformer eksplisit untuk versi lama.
- `schema_version`, resolver version, condition input, dan hasil clause list masuk snapshot/hash.
- Approval template menjalankan fixture test untuk seluruh branch kondisi yang didukung.

### 7.9 Versi konten dan artifact file

Tabel khusus lebih aman daripada memaksakan tabel `documents` existing. Pisahkan versi konten yang direview dari file hasil render agar approval selalu terikat ke snapshot yang tepat dan satu versi dapat memiliki beberapa draft/signed scan.

`offer_document_versions` menyimpan:

- `offer_id` dan `version_no`.
- `version_state`: `in_review`, `approved`, `finalized`, `superseded`, atau `void`.
- `template_version_id`, `issuer_profile_version_id`, dan `signer_version_id`.
- `data_snapshot` JSON dan `snapshot_sha256`.
- `approved_snapshot_sha256`.
- `approved_draft_artifact_id` dan `approved_render_profile_hash`.
- `submitted_by`, `submitted_at`.
- `approved_by`, `approved_at`.
- `finalized_by`, `finalized_at`.
- `supersedes_id` nullable.
- `lock_version`.

Unique constraint:

```text
(offer_id, version_no)
```

`offer_document_artifacts` menyimpan file:

- `offer_document_version_id`.
- `artifact_type`: `draft`, `final`, atau `signed_scan`.
- `artifact_no` agar beberapa draft/signed scan dapat disimpan pada versi yang sama.
- `final_slot` nullable; bernilai `1` hanya untuk artifact final.
- `storage_status`: `pending`, `ready`, `failed`, atau `void`.
- `generation_key` unik untuk idempotency.
- `source_draft_artifact_id` nullable untuk trace final ke draft approved.
- `file_path` private, `original_filename`, `mime_type`, dan `file_size`.
- `sha256`, `renderer_version`, `generated_by`, dan `generated_at`.

Unique constraint dasar:

```text
(offer_document_version_id, artifact_type, artifact_no)
```

Tambahkan unique `(offer_document_version_id, final_slot)` serta check bahwa `final_slot = 1` hanya untuk `artifact_type = final`. Karena artifact lain memakai null, database mengizinkan banyak draft/signed scan tetapi maksimal satu row final per version. Retry finalisasi memperbarui row final yang sama. `current_final_version_id` pada engagement adalah satu-satunya penunjuk final aktif antarversion.

`version_no` dialokasikan monotonik di bawah row lock Offer dan tidak pernah digunakan ulang. `artifact_no` dialokasikan di bawah lock version. Request paralel/retry harus memakai `generation_key` sehingga tidak dapat membuat dua final untuk snapshot yang sama.

Approval selalu diberikan pada `offer_document_versions.snapshot_sha256`, render profile, dan draft artifact tertentu. Finalizer wajib memverifikasi seluruh hash tersebut, lalu merender final dari snapshot approved yang sama. Perubahan data, template, issuer, signer/signature asset, renderer profile, atau input lain membatalkan approval dan memerlukan version/review baru. Transformasi final yang diizinkan hanya menghilangkan watermark draft dan menambahkan signature yang hash-nya sudah tercakup dalam snapshot approved.

## 8. Snapshot dan Immutability

Snapshot immutable dibuat ketika transition `ready_for_review → in_review`, sebelum draft review dirender. Snapshot tersebut menjadi satu-satunya source of truth untuk approval dan finalisasi. Sistem menyimpan:

- nomor dan tanggal surat;
- seluruh identitas pihak;
- seluruh objek dan dokumen kepemilikan;
- seluruh nilai fee, pajak, komponen biaya, dan termin;
- nilai terbilang;
- isi 25 klausul setelah kondisi diterapkan;
- versi template;
- profil penerbit;
- profil penandatangan;
- nama/path aset yang dipakai;
- versi renderer;
- waktu dan user penerbit.

Aturan:

- Draft review, approval, dan final selalu merujuk `offer_document_version` serta `snapshot_sha256` yang sama.
- Perubahan data setelah submit review membatalkan review aktif, mengembalikan workflow ke `data_draft`, dan membutuhkan version/snapshot baru.
- Finalisasi tidak membaca ulang Offer/Organization/Debtor live; finalizer hanya memverifikasi lalu memakai snapshot approved.
- PDF final lama selalu diunduh dari file yang tersimpan, bukan di-render ulang dari database terkini.
- Edit setelah finalisasi membuat revision/version baru tanpa mengubah final aktif lama.
- Final lama baru berubah menjadi `superseded` secara atomik setelah artifact final revisi berstatus `ready` dan pointer `current_final_version_id` berhasil dipindahkan.
- Hash SHA-256 diverifikasi ketika file disimpan dan saat audit bila diperlukan.
- Perubahan template, organisasi, tarif pajak, atau signatory tidak boleh mengubah dokumen lama.

## 9. Aturan Bisnis dan Preflight

Finalisasi ditolak jika salah satu kondisi berikut gagal:

### Identitas

- Nomor surat belum dialokasikan atau sudah digunakan.
- Profil cabang/letterhead belum aktif.
- Penerima, alamat, kota, atau perihal belum lengkap.
- Jenis referensi permintaan belum dipilih. Nomor/tanggal hanya wajib untuk `letter`/`email` bila template mensyaratkannya; `verbal`, `other`, atau `none` memerlukan konteks pembuka yang sesuai.
- Template belum berstatus approved.

### Pihak dan aset

- Tidak ada subject atau aset.
- Aset kehilangan jenis, alamat, atau dokumen kepemilikan yang diwajibkan template.
- Nomor dokumen kepemilikan terduplikasi dalam penawaran yang sama.
- Data yang ditampilkan pada deskripsi objek dan tabel fee tidak berasal dari record yang sama.

### Keuangan

- Fee item bernilai negatif.
- Subtotal, pajak, dan grand total tidak konsisten.
- Mode included/excluded belum dipilih.
- Tarif pajak tidak memiliki snapshot.
- Total termin tidak sama dengan 100%.
- Angka dan terbilang tidak cocok.

### Durasi dan konten

- Angka SLA dan teks durasi tidak berasal dari nilai yang sama.
- Klausul wajib hilang.
- Ada placeholder yang belum terisi.
- Konten melampaui batas layout tanpa review.
- Blok tanda tangan tidak muat pada halaman akhir.

Preflight menghasilkan daftar error dan warning. Error memblokir finalisasi; warning masih dapat dilanjutkan oleh reviewer dengan alasan yang tersimpan di audit log.

## 10. State Machine

Outcome komersial dan lifecycle dokumen harus dipisahkan.

### 10.1 Nilai outcome penawaran existing

Nilai yang tersedia saat ini adalah:

```text
DRAFT → DIKIRIM → DITERIMA
               ↘ TIDAK_LANJUT / DITOLAK
```

Diagram tersebut menggambarkan alur yang diharapkan, tetapi code existing belum menegakkan transition guard; pengguna edit dapat memilih outcome yang diizinkan validation secara langsung. Fase domain harus menetapkan transition matrix atau secara eksplisit mempertahankan outcome sebagai label bebas. Lifecycle dokumen di bawah tidak boleh bergantung pada asumsi transition yang belum ditegakkan.

### 10.2 Lifecycle dokumen baru

| State | Arti | Aksi berikutnya |
|---|---|---|
| `data_draft` | Data surat masih diedit. | Validasi/preflight. |
| `ready_for_review` | Seluruh error preflight selesai. | Buat draft/review. |
| `in_review` | Draft sedang diperiksa. | Revisi atau approve. |
| `approved` | Konten disetujui reviewer. | Finalisasi. |
| `finalized` | PDF final immutable tersedia. | Unduh/kirim. |
| `sent` | Pengiriman dicatat. | Terima/tolak/unggah scan. |
| `void` | Dibatalkan dengan alasan. | Hanya arsip. |

`offer_engagements.workflow_state` adalah satu-satunya owner state aktif. `offer_document_versions.version_state` hanya menjelaskan status versi immutable yang direview/final. Setiap transition menggunakan compare-and-swap terhadap `lock_version`, menyimpan actor, timestamp, alasan bila relevan, dan activity log. Transition yang tidak tercantum ditolak server-side.

```text
data_draft → ready_for_review → in_review → approved → finalized → sent
                 ↘ data_draft ←────┘          ↘ data_draft (data berubah)
non-final state → void (dengan alasan dan permission)
finalized/sent → data_draft (mulai revisi; final lama tetap aktif)
```

Ketika revisi dibuat, workflow aktif kembali ke `data_draft`, tetapi final lama tetap aktif. Final lama baru menjadi `superseded` saat final revisi berhasil diterbitkan; semua versi tetap dapat dilihat di riwayat. `signed_scan` adalah jenis file turunan dan tidak menggantikan PDF final asli.

## 11. Rancangan UI

### 11.1 Form Buat/Edit Penawaran

Form Penawaran yang ada dikembangkan menjadi section bertahap:

1. Identitas penawaran.
2. Penerima dan referensi permintaan.
3. Pemberi tugas dan pengguna laporan.
4. Pihak/debitur dan objek penilaian.
5. Lingkup, tujuan, dasar nilai, dan tanggal penilaian.
6. Bentuk laporan dan SLA.
7. Fee, pajak, biaya tambahan, dan termin.
8. Permintaan data awal.
9. Template, penerbit, dan penandatangan.
10. Review dan preflight.

Gunakan autosave draft atau tombol simpan per tahap agar input massal tidak hilang.

### 11.2 Editor pihak dan aset

- Tombol `Tambah pihak`.
- Di setiap pihak tersedia `Tambah aset`.
- Aset dapat diurutkan ulang.
- Data master Debitur dapat dipilih lalu disnapshot.
- Pengguna dapat menambah pihak ad-hoc dengan permission yang sesuai.
- Fee item dapat dihubungkan ke aset sehingga deskripsi dan tabel biaya selalu konsisten.

### 11.3 Halaman review

Layout desktop yang disarankan:

```text
Ringkasan/preflight  |  Pratinjau halaman PDF
```

Pada mobile, panel ditumpuk. Review menampilkan:

- error dan warning;
- versi template;
- profil penerbit;
- penandatangan;
- subtotal/pajak/total;
- jumlah pihak/aset;
- jumlah halaman hasil render;
- perbandingan terhadap versi sebelumnya.

### 11.4 Action pada daftar Penawaran

- Lengkapi dokumen.
- Pratinjau.
- Buat PDF draft.
- Ajukan review.
- Finalisasi, bila berizin.
- Unduh versi final.
- Buat revisi.
- Unggah salinan ditandatangani.
- Lihat riwayat versi.

Satu baris tabel tidak boleh menampilkan semua action sekaligus. Gunakan satu action utama berdasarkan state dan menu sekunder untuk riwayat/unduhan.

## 12. Service Layer

Service yang disarankan:

- `OfferNumberAllocator` — alokasi nomor atomik dan format suffix/kode.
- `OfferCommercialCalculator` — subtotal, tax inclusion, pajak, dan grand total.
- `IndonesianAmountSpeller` — terbilang dari integer Rupiah.
- `OfferPreflightValidator` — validasi lintas record dan layout warning.
- `OfferSnapshotBuilder` — membangun snapshot deterministik.
- `OfferClauseResolver` — menerapkan kondisi 25 klausul.
- `OfferDocumentRenderer` — mengubah print view menjadi PDF.
- `OfferDocumentFinalizer` — transaction, penyimpanan atomik, hash, dan audit.
- `OfferDocumentDownloadService` — authorization dan response file private.
- `OfferToWorkOrderConverter` — menyalin subject/aset secara transaction-safe saat penawaran diterima.

Kalkulasi dan finalisasi tidak ditempatkan langsung di method Livewire agar dapat diuji tanpa browser dan dipakai kembali oleh queue/command.

## 13. Pilihan Mesin PDF

Repository belum memiliki dependency PDF.

### Rekomendasi awal: Dompdf

Gunakan `dompdf/dompdf` untuk prototype pertama karena:

- berjalan langsung di PHP;
- tidak memerlukan proses browser terpisah;
- mendukung `@page`, tabel, gambar, dan font embedding;
- cocok untuk surat formal dengan layout block/table yang terkontrol.

Konsekuensi:

- print view tidak boleh memakai Tailwind layout modern, flexbox, atau CSS Grid;
- gunakan CSS print khusus berbasis block/table;
- tabel yang panjang perlu dipecah oleh aplikasi karena row Dompdf tidak pageable;
- local asset dibatasi melalui `chroot` dan remote fetching dinonaktifkan.

### Fallback: Browsershot

Jika spike visual membuktikan Dompdf tidak mampu mempertahankan odd/even header, pagination, atau tipografi sumber, gunakan Browsershot/Chromium. Environment lokal sudah memiliki Node 26 dan Chrome, tetapi deployment harus menyediakan Puppeteer, Chrome/Chromium, serta dependency OS. Karena biaya operasionalnya lebih tinggi, fallback ini dipilih hanya berdasarkan hasil visual diff.

Decision gate sebelum implementasi penuh:

1. Render halaman pembuka.
2. Render klausul objek massal.
3. Render tabel diskon dan fee panjang.
4. Render halaman tanda tangan.
5. Bandingkan Dompdf dengan sumber pada ukuran A4.
6. Lanjutkan Dompdf bila lolos; pindah ke Browsershot bila gagal.

Referensi teknis resmi:

- [Dompdf README](https://github.com/dompdf/dompdf) — dukungan CSS, font, image, `@page`, dan keterbatasan layout.
- [Browsershot introduction](https://spatie.be/docs/browsershot/v4/introduction) — rendering PDF melalui Puppeteer/Chrome.
- [Browsershot requirements](https://spatie.be/docs/browsershot/v4/requirements) — kebutuhan Node, Puppeteer, Chrome, dan dependency deployment.

## 14. Struktur Print View

File yang disarankan:

```text
resources/views/pdf/offers/
├── standard.blade.php
├── partials/
│   ├── letterhead.blade.php
│   ├── letter-meta.blade.php
│   ├── recipient.blade.php
│   ├── clause.blade.php
│   ├── subjects.blade.php
│   ├── discount-table.blade.php
│   ├── fee-summary.blade.php
│   ├── fee-table.blade.php
│   ├── requirements.blade.php
│   └── signatures.blade.php
└── print.css
```

Aturan:

- View hanya menerima immutable snapshot, bukan query model secara bebas.
- Setiap partial bersifat presentational.
- Legal copy sudah selesai di-resolve sebelum view dipanggil.
- Tidak ada request eksternal ketika render.
- Semua gambar berasal dari private/local approved asset.
- HTML dari editor disanitasi dengan allowlist terbatas.

## 15. Route, Action, dan Permission

Route binary menggunakan controller agar authorization dan response header dapat diuji dengan jelas:

```text
offers.documents.preview
offers.documents.download
offers.documents.signed-copy.download
```

Livewire menangani editor dan command UI, sedangkan service menangani domain operation.

Permission baru yang disarankan:

- `offers.documents.view`.
- `offers.documents.manage`.
- `offers.documents.generate-draft`.
- `offers.documents.review`.
- `offers.documents.finalize`.
- `offers.documents.apply-signature`.
- `offers.documents.download`.
- `offers.documents.upload-signed-copy`.
- `offers.templates.manage`.
- `offers.issuer-profiles.manage`.
- `offers.cross-branch`.

Mapping role harus diputuskan eksplisit. Permission `menu.offers` tidak cukup untuk memberi hak menempelkan signature atau menerbitkan dokumen final.

### 15.1 Policy dan branch scope

Seluruh query, `findOrFail`, Livewire action, preview, dan download harus melewati `OfferPolicy`/`OfferDocumentPolicy`, bukan hanya middleware menu.

Baseline scope:

| Pengguna | Scope record |
|---|---|
| Sysadmin | Seluruh cabang, tetap melalui policy. |
| User dengan `offers.cross-branch` | Cabang yang diizinkan oleh policy. |
| User lainnya | Hanya Offer dengan `branch_id` yang sama dengan user. |

Reviewer/finalizer/signature permission tidak otomatis memberi akses lintas cabang. Signer/issuer profile juga harus cocok dengan cabang Offer kecuali override eksplisit yang diaudit.

Test keamanan wajib mencoba ID milik cabang lain melalui URL preview/download, parameter Livewire edit/review/finalize, dan direct service call. Seluruhnya harus menghasilkan 403 tanpa membocorkan metadata atau keberadaan file.

## 16. Penyimpanan dan Keamanan

- Simpan PDF pada disk private, bukan `public`.
- Contoh path: `offers/{year}/{offer_id}/{uuid}.pdf`.
- Nama unduhan yang aman: `penawaran-{nomor-aman}-{klien-aman}-v{version}.pdf`.
- Pengunduhan selalu melalui route yang melakukan policy check.
- Jangan menerima path file dari public Livewire property.
- Signature, stamp, dan letterhead harus lolos validasi MIME, ukuran, dimensi, dan image decode.
- SVG tidak diterima untuk signature/stamp kecuali melalui sanitization yang ketat.
- Remote image/font fetching dimatikan pada renderer.
- File dirender ke temporary path dan di-hash sebelum transaction finalisasi.
- Request generate/finalize memakai `generation_key` idempotent dan lock per Offer/version agar double-click atau retry tidak membuat versi/file ganda.
- Record artifact dibuat dengan status `pending`; file lalu dipindahkan secara atomik ke final path dan status diubah menjadi `ready` dalam alur finalisasi terkoordinasi.
- Karena database dan filesystem tidak berbagi transaction, kegagalan setelah salah satu sisi berhasil menjalankan compensating cleanup/retry. Scheduled reconciler mengubah pending yang gagal menjadi `failed` dan membersihkan file orphan secara aman.
- Kegagalan render tidak boleh menghasilkan artifact yang dapat diunduh sebagai final.
- Signed scan divalidasi melalui magic bytes dan parser PDF, bukan extension/MIME dari browser saja; tetapkan batas ukuran, jumlah halaman, dan quarantine/antivirus bila tersedia pada environment produksi.
- Immutable asset dan PDF final masuk kebijakan backup, restore test, encryption at rest bila didukung storage, serta retention/legal hold yang disetujui.
- Download dan finalisasi dicatat pada audit log.
- Data sensitif tidak ditulis ke log exception.
- Preview draft memiliki watermark `DRAFT — BELUM DISETUJUI`.
- Finalisasi dan penggunaan signature memerlukan re-authorization sesuai tingkat risiko yang disepakati.

## 17. Integrasi dengan Konversi Pekerjaan

Saat penawaran diterima dan dikonversi:

- proses dibungkus database transaction;
- hanya satu WorkOrder boleh dibuat dari satu Offer;
- tambahkan unique index pada `work_orders.offer_id` (nilai null untuk pekerjaan legacy tetap diizinkan) setelah audit duplicate existing;
- Offer dikunci ketika konversi dan retry mengembalikan WorkOrder existing, bukan membuat record kedua;
- seluruh `offer_assets` disalin ke `work_order_assets`;
- field kepemilikan/luas yang belum ada pada `work_order_assets` perlu ditambahkan atau dipetakan secara eksplisit;
- perilaku existing secara eksplisit memakai `offer_no` sebagai `contract_no` dan menyebutnya locked decision; rencana awal mempertahankan perilaku tersebut. Jika owner ingin nomor kontrak berbeda, keputusan dan migrasinya harus dibuat sebelum fase integrasi;
- PDF final penawaran dapat direferensikan oleh WorkOrder tanpa diduplikasi secara fisik;
- status history dan audit log dibuat satu kali;
- retry tidak boleh membuat WorkOrder atau history ganda.

## 18. Strategi Pengujian

### 18.1 Unit test

- Nomor surat normal, suffix, bulan Romawi, dan reset tahun.
- Alokasi nomor paralel tidak menghasilkan duplikasi.
- Format tanggal Indonesia.
- Format Rupiah dan terbilang.
- Kalkulasi included/excluded tax.
- Total fee item dan termin.
- Validator total termin 100%.
- Validator duplicate certificate.
- Resolver klausul berdasarkan purpose/basis.
- Snapshot deterministik.
- Hash file.
- State transition legal/ilegal.

### 18.2 Feature test

- Create/Edit tetap kompatibel dengan data Penawaran lama.
- Banyak subject dan aset dapat disimpan serta diurutkan.
- Preflight menampilkan error yang benar.
- Pengguna tanpa permission tidak dapat preview/finalize/download.
- Draft memiliki watermark.
- Final tersimpan private dan dapat diunduh user berizin.
- Edit setelah final membuat versi baru.
- Final lama tidak berubah setelah master/template diedit.
- Approval hanya berlaku untuk snapshot hash dan draft artifact yang direview.
- Finalizer menolak snapshot hash yang berbeda dari approval.
- Final lama tetap aktif selama revisi masih draft dan baru superseded setelah final baru ready.
- Signature tidak dapat digunakan tanpa permission.
- Signed scan tersimpan sebagai dokumen terpisah.
- Banyak signed scan pada version yang sama tidak melanggar constraint.
- Konversi menyalin semua aset tepat satu kali.
- Semua action penting menghasilkan audit log.

### 18.3 PDF contract test

- Response diawali signature `%PDF-`.
- MIME `application/pdf` dan filename benar.
- Media box A4 pada seluruh halaman.
- Teks wajib dari 25 klausul tersedia.
- Jumlah dan total fee sesuai snapshot.
- Draft/final menampilkan atau menghilangkan watermark sesuai state.
- Hash database sama dengan file.

### 18.4 Golden/visual test

Buat fixture tersanitasi yang mewakili tiga pola sumber:

- simple + pajak excluded + termin;
- simple + pajak included;
- bulk 34 pihak/aset + tabel fee + basis nilai tambahan.

Render setiap halaman menjadi gambar dan bandingkan terhadap baseline yang telah disetujui. PDF asli yang mengandung data klien tidak dimasukkan ke fixture repository.

Manual UAT wajib memeriksa:

- print preview 100%;
- hasil cetak A4;
- page break;
- odd/even letterhead;
- font dan wrapping;
- tabel panjang;
- signature alignment;
- pembukaan di Chrome, Edge, dan Adobe Reader.

## 19. Tahapan Penerapan

### Fase 0 — Keputusan dan aset resmi

- Setujui redaksi 25 klausul.
- Tentukan apakah typo sumber dipertahankan atau diperbaiki.
- Tentukan odd-page header atau header setiap halaman.
- Tetapkan formula fee/pajak yang sah.
- Tetapkan pola nomor dan arti setiap segmen.
- Tetapkan scope/waktu alokasi nomor, aturan suffix, dan perlakuan void/gap.
- Selesaikan clause ledger serta fixture tersanitasi A/B/C.
- Siapkan letterhead, font, signature, dan stamp resmi.
- Tentukan role reviewer/finalizer.
- Setujui branch-scope policy dan target visual/performance baseline.

Gate: tidak ada template produksi sebelum owner legal/operasional menyetujui sumber teks dan aset.

### Fase 1 — Fondasi domain

- Tambah migrations/model relasi baru.
- Tambah allocator nomor atomik.
- Tambah calculator dan terbilang.
- Tambah snapshot builder dan preflight validator.
- Tambah state owner, version/artifact model, lock, dan approval hash contract.
- Pertahankan workflow Penawaran existing.

Gate: unit test domain dan compatibility test lulus.

### Fase 2 — Master template dan profil

- UI profil penerbit/cabang.
- UI penandatangan.
- Template dan version approval.
- Private asset management.
- Content-addressed immutable asset version.
- Permission baru.

Gate: hanya versi approved yang dapat dipakai finalisasi.

### Fase 3 — Editor dokumen penawaran

- Section penerima dan referensi.
- Repeatable subject/aset.
- Lingkup/basis/output.
- Fee item, pajak, termin, dan requirement.
- Autosave draft dan preflight UI.

Gate: tiga fixture sumber dapat direpresentasikan tanpa field bebas yang berlebihan.

### Fase 4 — Spike dan renderer PDF

- Prototype Dompdf pada empat halaman tersulit.
- Pilih Dompdf atau fallback Browsershot.
- Implement print partial dan CSS final.
- Draft watermark dan preview.
- Text/PDF contract tests.

Gate: visual sign-off pada fixture simple dan bulk.

### Fase 5 — Review, finalisasi, dan arsip

- State machine.
- Approval/finalization policy.
- Immutable snapshot.
- Private storage dan download.
- Version history dan supersede.
- Signature permission serta signed scan.
- Audit log.

Gate: security test, versioning test, dan recovery test lulus.

### Fase 6 — Integrasi WorkOrder

- Transactional conversion.
- Salin seluruh aset.
- Idempotency.
- Referensi PDF final.
- Regression test workflow lama.

Gate: satu Offer menghasilkan maksimal satu WorkOrder dan satu initial history.

### Fase 7 — UAT dan rollout

- UAT tiga skenario tersanitasi.
- Cetak fisik dan approval legal/operasional.
- Backfill data existing sebagai legacy.
- Dokumentasi pengguna.
- Monitoring error render dan storage.
- Rollout bertahap per role/cabang.

## 20. Strategi Migrasi Data Lama

- Migration bersifat additive; jangan menghapus field existing pada fase awal.
- Offer lama diberi status `legacy_incomplete` untuk dokumen otomatis.
- `debtor_id` lama dibuat sebagai primary `offer_subject` ketika user pertama kali membuka editor dokumen.
- Organization dan Debtor disnapshot; perubahan master sesudahnya tidak mengubah versi final.
- Nilai fee/dpp/ppn/pph lama tidak otomatis dianggap sesuai aturan dokumen.
- User harus memilih tax inclusion dan mengonfirmasi fee sebelum draft pertama dibuat.
- Tidak ada PDF lama yang direkonstruksi otomatis tanpa review.

## 21. Acceptance Criteria

Fitur dianggap siap produksi jika:

1. Pengguna dapat membuat penawaran simple dan bulk dari UI tanpa menyunting Word.
2. Urutan 25 klausul sama dengan template approved.
3. Layout A4, letterhead, blok penerima, grid klausul, fee, dan tanda tangan mengikuti format sumber.
4. Fixture source-equivalent A/C menghasilkan tepat 5 halaman dan fixture B dengan 34 pihak menghasilkan tepat 13 halaman setelah baseline disetujui, tanpa clipping.
5. Deskripsi objek dan tabel fee selalu berasal dari record yang sama.
6. Nomor urut aset, SLA, total, pajak, dan terbilang konsisten otomatis.
7. Draft jelas berbeda dari final.
8. PDF final immutable, terversi, memiliki hash, dan dapat diunduh kembali.
9. Perubahan master/template tidak mengubah PDF final lama.
10. Pengguna tanpa permission tidak dapat finalisasi, memakai signature, atau mengunduh dokumen.
11. Seluruh generate, review, finalisasi, supersede, upload signed scan, dan download tercatat.
12. Konversi Offer ke WorkOrder bersifat transactional dan idempotent.
13. Test unit, feature, PDF contract, visual regression, dan UAT lulus.
14. Owner legal/operasional menyetujui redaksi dan hasil cetak.

Quality gate terukur setelah baseline Fase 0 disetujui:

- Fixture source-equivalent A dan C masing-masing tepat 5 halaman; fixture B tepat 13 halaman. Fixture lain mengikuti expected page count yang disimpan bersama baseline.
- Seluruh halaman memiliki media box A4 dengan toleransi maksimal 1 pt.
- Tidak ada content bounding box keluar dari printable area dan tidak ada teks/tabel/signature yang terpotong.
- Text extraction menemukan seluruh `clause_key` 1–25 dan seluruh token wajib fixture.
- Visual diff pada environment renderer yang dipin, 144 DPI, maksimal 1% pixel berbeda setelah masking field dinamis yang disetujui; threshold dapat diperketat pada sign-off.
- Render simple selesai maksimal 3 detik dan bulk maksimal 10 detik, peak memory maksimal 256 MB, pada runner referensi yang dicatat di baseline.
- PDF digital tanpa signed scan maksimal 5 MB; pengecualian harus menjadi warning preflight.
- Dua puluh request nomor paralel menghasilkan 20 nomor unik; sepuluh retry finalisasi dengan idempotency key sama menghasilkan tepat satu final artifact.
- Seluruh percobaan IDOR lintas cabang pada URL, Livewire action, dan service menghasilkan 403/authorization exception.
- Simulasi crash pada setiap tahap `pending → ready` dapat dipulihkan reconciler tanpa final palsu atau file orphan.
- Bukti approval template/fixture menyimpan nama approver, tanggal, hash baseline, dan renderer version.

## 22. Keputusan yang Masih Dibutuhkan

Sebelum coding dimulai, pemilik proses perlu menjawab:

1. Apakah letterhead hanya di halaman ganjil seperti sumber atau pada setiap halaman?
2. Apakah typo/ejaan lama diperbaiki dalam versi template pertama?
3. Apakah dokumen Penawaran juga dianggap SPK seperti kalimat pada sumber?
4. Apa arti segmen kode sebelum bulan pada nomor surat, dan apakah berbeda per cabang?
5. Apakah sequence global per tahun tetap dipakai, dan kapan nomor resmi dialokasikan?
6. Bagaimana kebijakan gap/void nomor serta suffix seperti `.A`; manual, nomor independen, atau revisi?
7. Apakah fee pada UI adalah nilai sebelum pajak, sesudah pajak, atau dapat dipilih per penawaran?
8. Apakah PPh hanya kalkulasi internal atau perlu muncul di dokumen?
9. Siapa yang boleh approve, finalize, dan memakai signature/stamp?
10. Role mana yang boleh mengakses penawaran lintas cabang?
11. Apakah versi final awal menggunakan signature image atau selalu dicetak untuk tanda tangan basah?
12. Apakah klien menerima PDF melalui aplikasi/email atau pengiriman tetap manual?
13. Apakah template berbeda diperlukan per bank/tujuan, atau cukup satu template dengan kondisi?
14. Berapa lama PDF, snapshot, signature asset, dan audit log harus disimpan?

## 23. Di Luar Scope Versi Pertama

- Tanda tangan digital tersertifikasi/PKI.
- Pengiriman WhatsApp otomatis.
- Portal persetujuan klien.
- Editor bebas seperti Microsoft Word.
- OCR otomatis terhadap scan persetujuan.
- Rekonstruksi seluruh PDF historis.
- Perubahan substansi legal tanpa persetujuan pemilik proses.

## 24. Rencana File Implementasi

Perkiraan file yang akan ditambah/diubah pada fase coding:

```text
composer.json
composer.lock
config/offer-documents.php
database/migrations/*_create_offer_engagements_table.php
database/migrations/*_create_offer_subjects_table.php
database/migrations/*_create_offer_assets_table.php
database/migrations/*_create_offer_asset_documents_table.php
database/migrations/*_create_offer_fee_items_table.php
database/migrations/*_create_offer_payment_terms_table.php
database/migrations/*_create_offer_requirements_table.php
database/migrations/*_create_offer_number_counters_table.php
database/migrations/*_create_offer_number_allocations_table.php
database/migrations/*_add_current_number_allocation_id_to_offers_table.php
database/migrations/*_create_offer_templates_table.php
database/migrations/*_create_offer_template_versions_table.php
database/migrations/*_create_issuer_profile_versions_table.php
database/migrations/*_create_document_signer_versions_table.php
database/migrations/*_create_offer_document_versions_table.php
database/migrations/*_create_offer_document_artifacts_table.php
database/migrations/*_enforce_unique_offer_on_work_orders_table.php
app/Models/*
app/Policies/OfferDocumentPolicy.php
app/Services/Offers/*
app/Livewire/Offers/DocumentEditor.php
app/Http/Controllers/OfferDocumentController.php
resources/views/livewire/offers/document-editor.blade.php
resources/views/pdf/offers/*
tests/Unit/Offers/*
tests/Feature/OfferDocumentWorkflowTest.php
tests/Feature/OfferDocumentAuthorizationTest.php
tests/Feature/OfferDocumentPdfTest.php
```

Daftar ini adalah rancangan, bukan instruksi untuk membuat semua file sekaligus. Implementasi tetap dilakukan per fase dan setiap fase harus lulus gate sebelum berlanjut.

## 25. Langkah Pertama yang Direkomendasikan

Mulai dari Fase 0, kemudian buat vertical slice kecil:

1. Satu template approved.
2. Satu issuer profile.
3. Satu signatory tanpa signature image terlebih dahulu.
4. Satu penawaran simple dengan satu subject/aset.
5. Preview halaman pembuka, klausul biaya, dan tanda tangan.
6. Golden visual test.

Setelah slice simple stabil, baru tambahkan nested subject/aset dan tabel fee untuk skenario BNI massal. Urutan ini mengurangi risiko mengembangkan editor besar sebelum kualitas PDF dasar terbukti.
