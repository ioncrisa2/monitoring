# Golden visual penawaran

Harness ini memakai empat fixture anonim untuk menjaga bentuk halaman hasil audit tanpa menyimpan PDF sumber atau data pelanggan:

| Fixture | Kategori | Bentuk referensi | Halaman |
| --- | --- | --- | ---: |
| `collateral-multi` | `property-collateral` | Caraka | 5 |
| `collateral-detailed` | `property-collateral` | Bank Index | 6 |
| `auction-twelve-assets` | `property-auction` | Lelang Mandiri | 9 |
| `rental-market` | `property-rental` | Nilai Sewa | 5 |

Suite PHP biasa selalu memverifikasi jumlah halaman, MediaBox A4 portrait, kontrak kop tetap pada setiap halaman, pengulangan header tabel, pencegahan pemotongan baris dan blok tanda tangan basah, serta ketiadaan footer/nomor halaman. Fixture dibuat dari redaksi, identitas, nomor dokumen, dan kop sintetis.

## Golden PNG 144 DPI

Perbandingan raster bersifat opt-in karena membutuhkan `pdftoppm` dari Poppler dan ekstensi PHP GD. Jalankan dari root repository:

```powershell
composer update:offer-visual
```

Perintah tersebut merender semua halaman pada 144 DPI (`1191 x 1684` piksel), lalu menulis baseline ke `tests/Visual/Offers/baselines/<fixture>/`. Periksa seluruh PNG secara manual—terutama kop setiap halaman, header tabel lintas halaman, kolom yang terpotong, dan blok tanda tangan—sebelum melakukan commit.

Setelah baseline ditinjau dan disimpan, jalankan pemeriksaan:

```powershell
composer test:offer-visual
```

Atau aktifkan pemeriksaan yang sama di PHPUnit/CI:

```powershell
$env:OFFER_VISUAL_REGRESSION='1'
php artisan test tests/Feature/OfferDocumentVisualContractTest.php
```

Jika Poppler tidak berada di `PATH`, isi `PDFTOPPM_BIN` dengan path absolut ke executable. Pembanding mengizinkan perbedaan antialiasing kecil, tetapi menolak perubahan dimensi atau pergeseran visual yang bermakna. Baseline hanya boleh diperbarui setelah inspeksi visual dan UAT cetak; jangan memperbaruinya otomatis di CI.
