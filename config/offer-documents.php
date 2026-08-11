<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output contract
    |--------------------------------------------------------------------------
    |
    | The application only produces an unsigned PDF for physical printing.
    | Signatures and stamps are applied with wet ink after the file is printed;
    | delivery to the client happens outside the application.
    |
    */
    'output' => [
        'workflow' => 'physical_print',
        'embedded_signature' => false,
        'embedded_stamp' => false,
        'signed_scan' => false,
        'digital_delivery' => false,
    ],

    'renderer' => [
        'engine' => 'dompdf',
        'version' => 'dompdf-3.1',
        'paper' => 'a4',
        'orientation' => 'portrait',
        'dpi' => 96,
        'default_font' => 'DejaVu Sans',
        'compress' => true,
        'header_mode' => 'odd_pages',
        'temp_path' => storage_path('framework/cache/dompdf/temp'),
        'font_cache_path' => storage_path('framework/cache/dompdf/fonts'),
        'approved_asset_path' => storage_path('app/private/offer-document-assets'),
    ],

    'provisional' => [
        'issuer' => [
            'name' => 'KJPP — PROFIL PENERBIT DRAF',
            'address_lines' => [
                '[DRAF] Alamat kantor penerbit belum dikonfigurasi.',
            ],
            'contact_lines' => [
                '[DRAF] Kontak penerbit belum dikonfigurasi.',
            ],
        ],
        'opening' => 'DRAF — Sehubungan dengan permintaan jasa penilaian yang diterima, dokumen ini disusun sebagai pratinjau internal. Redaksi pembuka belum mendapat persetujuan legal dan operasional.',
        'closing' => 'DRAF — Dokumen ini hanya untuk pratinjau internal, belum merupakan penawaran final, dan belum dapat digunakan sebagai dasar penugasan.',
        'clause_paragraphs' => [
            'appraiser_status' => 'DRAF — Profil dan status penilai akan diambil dari profil penerbit serta penandatangan yang telah disetujui.',
            'client' => 'DRAF — Identitas Pemberi Tugas akan diambil dari snapshot organisasi pada penawaran.',
            'report_user' => 'DRAF — Identitas Pengguna Laporan akan diambil dari snapshot organisasi pada penawaran.',
            'valuation_object' => 'DRAF — Uraian objek penilaian akan dibentuk dari subject dan aset yang telah diverifikasi.',
            'ownership_form' => 'DRAF — Bentuk kepemilikan akan diambil dari data aset yang telah diverifikasi.',
            'currency' => 'DRAF — Mata uang penugasan akan mengikuti data komersial yang telah disetujui.',
            'purpose' => 'DRAF — Maksud dan tujuan penilaian belum mendapat persetujuan legal dan operasional.',
            'basis_of_value' => 'DRAF — Dasar nilai dan kondisi tambahannya belum mendapat persetujuan legal dan operasional.',
            'valuation_date' => 'DRAF — Aturan tanggal penilaian belum mendapat persetujuan legal dan operasional.',
            'investigation_depth' => 'DRAF — Tingkat kedalaman investigasi belum mendapat persetujuan legal dan operasional.',
            'information_sources' => 'DRAF — Redaksi sifat dan sumber informasi belum mendapat persetujuan legal dan operasional.',
            'assumptions' => 'DRAF — Asumsi dan asumsi khusus belum mendapat persetujuan legal dan operasional.',
            'publication_approval' => 'DRAF — Persyaratan publikasi belum mendapat persetujuan legal dan operasional.',
            'valuation_standard' => 'DRAF — Standar dan edisi yang berlaku belum ditetapkan pada template yang disetujui.',
            'valuation_report' => 'DRAF — Bentuk, bahasa, dan jumlah salinan laporan akan mengikuti data penugasan yang disetujui.',
            'liability_limit' => 'DRAF — Batasan tanggung jawab belum mendapat persetujuan legal dan operasional.',
            'client_declaration' => 'DRAF — Redaksi pernyataan Pemberi Tugas belum mendapat persetujuan legal dan operasional.',
            'professional_fee' => 'DRAF — Biaya, pajak, komponen yang termasuk, dan termin akan dihitung dari snapshot komersial yang disetujui.',
            'initial_data_request' => 'DRAF — Daftar permintaan data awal akan dibentuk dari requirement penawaran yang telah diverifikasi.',
            'completion_time' => 'DRAF — Kerangka waktu akan dibentuk dari satu nilai durasi yang telah disetujui.',
            'assignment_procedure' => 'DRAF — Prosedur pelaksanaan penugasan belum mendapat persetujuan legal dan operasional.',
            'cancellation' => 'DRAF — Ketentuan pembatalan belum mendapat persetujuan legal dan operasional.',
            'confidentiality' => 'DRAF — Ketentuan kerahasiaan belum mendapat persetujuan legal dan operasional.',
            'closing' => 'DRAF — Redaksi penutup klausul belum mendapat persetujuan legal dan operasional.',
            'other_terms' => 'DRAF — Ketentuan lain-lain belum mendapat persetujuan legal dan operasional.',
        ],
    ],

    'clause_titles' => [
        'appraiser_status' => 'Status Penilai',
        'client' => 'Pemberi Tugas',
        'report_user' => 'Pengguna Laporan',
        'valuation_object' => 'Objek Penilaian',
        'ownership_form' => 'Bentuk Kepemilikan',
        'currency' => 'Jenis Mata Uang yang Digunakan',
        'purpose' => 'Maksud dan Tujuan Penilaian',
        'basis_of_value' => 'Dasar Nilai',
        'valuation_date' => 'Tanggal Penilaian',
        'investigation_depth' => 'Tingkat Kedalaman Investigasi',
        'information_sources' => 'Sifat dan Sumber Informasi yang Dapat Diandalkan',
        'assumptions' => 'Asumsi dan Asumsi Khusus',
        'publication_approval' => 'Persyaratan atas Persetujuan untuk Publikasi',
        'valuation_standard' => 'Standar Penilaian',
        'valuation_report' => 'Laporan Penilaian',
        'liability_limit' => 'Batasan atau Pengecualian Tanggung Jawab kepada Pihak Selain Pemberi Tugas',
        'client_declaration' => 'Pernyataan Tertulis Pemberi Tugas tentang Kebenaran Informasi',
        'professional_fee' => 'Biaya Jasa Penilaian',
        'initial_data_request' => 'Permintaan Data Awal',
        'completion_time' => 'Kerangka Waktu Pelaksanaan',
        'assignment_procedure' => 'Prosedur Pelaksanaan Penugasan',
        'cancellation' => 'Pembatalan Penugasan',
        'confidentiality' => 'Kerahasiaan Informasi',
        'closing' => 'Penutup',
        'other_terms' => 'Lain-lain',
    ],
];
