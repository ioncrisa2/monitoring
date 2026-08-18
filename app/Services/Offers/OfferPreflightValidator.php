<?php

namespace App\Services\Offers;

use DomainException;

class OfferPreflightValidator
{
    public const MODE_DRAFT = 'draft';

    public const MODE_PRINT_READY = 'print_ready';

    /** @deprecated Use MODE_PRINT_READY. */
    public const MODE_REVIEW = self::MODE_PRINT_READY;

    /** @deprecated Use MODE_PRINT_READY. */
    public const MODE_FINAL = self::MODE_PRINT_READY;

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validate(array $snapshot, string $mode = self::MODE_DRAFT): array
    {
        if (! in_array($mode, [self::MODE_DRAFT, self::MODE_PRINT_READY], true)) {
            throw new DomainException('Mode preflight tidak valid.');
        }

        $strict = $mode !== self::MODE_DRAFT;
        $errors = [];
        $warnings = [];
        $flag = function (bool $condition, string $message) use (&$errors, &$warnings, $strict): void {
            if (! $condition) {
                return;
            }

            if ($strict) {
                $errors[] = $message;
            } else {
                $warnings[] = $message;
            }
        };

        foreach (['document', 'issuer', 'recipient', 'clauses', 'signatures'] as $section) {
            if (! isset($snapshot[$section]) || ! is_array($snapshot[$section])) {
                $errors[] = "Bagian snapshot {$section} tidak tersedia.";
            }
        }

        if ($errors !== []) {
            return ['errors' => array_values(array_unique($errors)), 'warnings' => []];
        }

        foreach (['number', 'place', 'date', 'subject', 'opening', 'closing'] as $field) {
            if (! $this->filled($snapshot['document'][$field] ?? null)) {
                $errors[] = "Nilai document.{$field} wajib tersedia.";
            }
        }

        $titles = (array) config('offer-documents.clause_titles', []);
        $clauses = $snapshot['clauses'];

        if (! array_is_list($clauses) || count($clauses) !== 25 || count($titles) !== 25) {
            $errors[] = 'Snapshot harus memiliki tepat 25 klausul terurut.';
        } else {
            foreach (array_values($titles) as $index => $title) {
                $clause = $clauses[$index] ?? null;
                $key = array_keys($titles)[$index];

                if (! is_array($clause)
                    || ($clause['number'] ?? null) !== $index + 1
                    || ($clause['key'] ?? null) !== $key
                    || ($clause['title'] ?? null) !== $title
                    || (($clause['paragraphs'] ?? []) === [] && ($clause['items'] ?? []) === [])) {
                    $errors[] = 'Klausul '.($index + 1).' tidak memenuhi kontrak urutan dan konten.';

                    continue;
                }

                $contentRows = array_merge($clause['paragraphs'] ?? [], $clause['items'] ?? []);

                if (count($contentRows) > 200) {
                    $errors[] = 'Klausul '.($index + 1).' melebihi batas 200 baris konten.';
                }

                foreach ($contentRows as $content) {
                    if (! is_string($content) || mb_strlen($content) > 2000) {
                        $errors[] = 'Konten klausul '.($index + 1).' harus berupa teks maksimal 2000 karakter per baris.';
                        break;
                    }
                }
            }
        }

        $metadata = is_array($snapshot['metadata'] ?? null) ? $snapshot['metadata'] : [];
        $engagement = is_array($snapshot['engagement'] ?? null) ? $snapshot['engagement'] : [];
        $subjects = is_array($snapshot['subjects'] ?? null) ? $snapshot['subjects'] : [];
        $commercial = is_array($snapshot['commercial'] ?? null) ? $snapshot['commercial'] : [];
        $requirements = is_array($snapshot['requirements'] ?? null) ? $snapshot['requirements'] : [];

        $flag(($metadata['number_allocation']['status'] ?? null) !== 'allocated', 'Nomor surat resmi belum dialokasikan.');
        $this->validateApprovedMaster(
            is_array($metadata['template'] ?? null) ? $metadata['template'] : [],
            'Versi template legal',
            $flag,
            true,
        );
        $this->validateApprovedMaster(
            is_array($metadata['issuer_profile'] ?? null) ? $metadata['issuer_profile'] : [],
            'Profil penerbit',
            $flag,
        );
        $this->validateApprovedMaster(
            is_array($metadata['signer'] ?? null) ? $metadata['signer'] : [],
            'Profil penandatangan',
            $flag,
        );
        $flag(! $this->filled($engagement['recipient_organization'] ?? null), 'Nama organisasi penerima belum diisi.');
        $flag(! $this->filled($engagement['recipient_address'] ?? null), 'Alamat penerima belum diisi.');
        $flag(! $this->filled($engagement['recipient_city'] ?? null), 'Kota penerima belum diisi.');
        $flag(! $this->filled($engagement['issue_city'] ?? null), 'Kota penerbitan belum diisi.');
        $flag(! $this->filled($engagement['purpose'] ?? null), 'Tujuan penilaian belum diisi.');
        $flag(! $this->filled($engagement['valuation_basis'] ?? null), 'Dasar nilai belum diisi.');
        $flag(
            ! $this->filled($engagement['valuation_date'] ?? null)
                && ! $this->filled($engagement['valuation_date_rule'] ?? null),
            'Tanggal penilaian atau aturan tanggal penilaian belum diisi.',
        );
        $flag(! $this->filled($engagement['ownership_form'] ?? null), 'Bentuk kepemilikan belum diisi.');
        $flag(! $this->filled($engagement['investigation_level'] ?? null), 'Tingkat investigasi belum diisi.');
        $flag(! $this->filled($engagement['report_format'] ?? null), 'Format laporan belum diisi.');
        $flag(
            ! is_int($engagement['report_copies'] ?? null) || $engagement['report_copies'] < 1,
            'Jumlah eksemplar laporan harus sedikitnya satu.',
        );
        $flag(! $this->filled($engagement['completion_days'] ?? null), 'Durasi penyelesaian belum diisi.');
        $flag(! $this->filled($engagement['completion_day_type'] ?? null), 'Jenis hari penyelesaian belum diisi.');
        $flag(! $this->filled($engagement['tax_inclusion'] ?? null), 'Mode pajak belum dipilih.');
        $flag(! $this->filled($snapshot['issuer']['name'] ?? null), 'Nama penerbit belum diisi.');
        $flag(! $this->filledTextList($snapshot['issuer']['address_lines'] ?? null), 'Alamat penerbit belum diisi.');
        $flag(! $this->filled($snapshot['signatures']['issuer_name'] ?? null), 'Nama penandatangan belum diisi.');
        $flag(! $this->filled($snapshot['signatures']['issuer_title'] ?? null), 'Jabatan penandatangan belum diisi.');

        if (in_array($engagement['request_reference_type'] ?? 'none', ['letter', 'email'], true)) {
            $flag(! $this->filled($engagement['request_reference_no'] ?? null), 'Nomor referensi permintaan belum diisi.');
            $flag(! $this->filled($engagement['request_reference_date'] ?? null), 'Tanggal referensi permintaan belum diisi.');
        }

        $primarySubjects = 0;
        $assetCount = 0;
        $documentKeys = [];

        foreach ($subjects as $subject) {
            if (! is_array($subject)) {
                $errors[] = 'Struktur subject tidak valid.';

                continue;
            }

            $primarySubjects += ($subject['is_primary'] ?? false) ? 1 : 0;

            foreach (($subject['assets'] ?? []) as $asset) {
                if (! is_array($asset)) {
                    $errors[] = 'Struktur aset tidak valid.';

                    continue;
                }

                $assetCount++;
                $flag(! $this->filled($asset['asset_type'] ?? null), 'Jenis aset belum diisi.');
                $flag(! $this->filled($asset['address'] ?? null), 'Alamat aset belum diisi.');
                $documents = is_array($asset['documents'] ?? null) ? $asset['documents'] : [];
                $flag($documents === [], 'Dokumen kepemilikan aset belum diisi.');

                foreach ($documents as $document) {
                    if (! is_array($document)) {
                        $errors[] = 'Struktur dokumen kepemilikan tidak valid.';

                        continue;
                    }

                    $flag(! $this->filled($document['document_type'] ?? null), 'Jenis dokumen kepemilikan belum diisi.');
                    $flag(! $this->filled($document['document_no'] ?? null), 'Nomor dokumen kepemilikan belum diisi.');

                    $key = mb_strtolower(trim((string) ($document['document_type'] ?? '')).'|'.trim((string) ($document['document_no'] ?? '')));

                    if (isset($documentKeys[$key])) {
                        $errors[] = 'Nomor dokumen kepemilikan terduplikasi dalam penawaran.';
                    }

                    $documentKeys[$key] = true;
                }
            }
        }

        $flag($subjects === [], 'Penawaran belum memiliki subject.');
        $flag($primarySubjects !== 1, 'Penawaran harus memiliki tepat satu subject utama.');
        $flag($assetCount === 0, 'Penawaran belum memiliki aset.');
        $flag($requirements === [], 'Permintaan data awal belum diisi.');

        foreach (($commercial['calculation_errors'] ?? []) as $calculationError) {
            $errors[] = 'Perhitungan komersial: '.$calculationError;
        }

        if (($commercial['payment_terms'] ?? []) !== []
            && ($commercial['payment_term_bps_total'] ?? null) !== 10_000) {
            $errors[] = 'Total persentase termin harus tepat 100%.';
        }

        if (($commercial['quoted_amount'] ?? 0) < 0
            || ($commercial['tax_base'] ?? 0) < 0
            || ($commercial['ppn'] ?? 0) < 0
            || ($commercial['document_payable_total'] ?? 0) < 0) {
            $errors[] = 'Nilai komersial tidak boleh negatif.';
        }

        $flag(($commercial['line_items'] ?? []) === [], 'Item fee belum diisi.');
        $flag(($commercial['document_payable_total'] ?? 0) === 0, 'Total nilai penawaran masih nol.');

        if ($strict && ($metadata['uses_provisional_copy'] ?? true)) {
            $errors[] = 'Redaksi provisional DRAF tidak boleh digunakan untuk PDF siap cetak.';
        }

        if ($strict && ($metadata['uses_provisional_issuer'] ?? true)) {
            $errors[] = 'Profil penerbit provisional tidak boleh digunakan untuk PDF siap cetak.';
        }

        if ($strict && OfferDocumentContentGuard::containsProvisionalMarker([
            $snapshot['document'],
            $snapshot['issuer'],
            $snapshot['recipient'],
            $snapshot['clauses'],
            $snapshot['signatures'],
        ])) {
            $errors[] = 'PDF siap cetak tidak boleh memuat penanda DRAF atau redaksi provisional.';
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function filled(mixed $value): bool
    {
        return ! ($value === null || $value === '' || (is_string($value) && trim($value) === ''));
    }

    private function filledTextList(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $master
     * @param  callable(bool, string): void  $flag
     */
    private function validateApprovedMaster(
        array $master,
        string $label,
        callable $flag,
        bool $requiresActiveTemplate = false,
    ): void {
        $approved = ($master['status'] ?? null) === 'approved';
        $flag(! $approved, "{$label} belum disetujui.");

        if (! $approved) {
            return;
        }

        $flag(! $this->filled($master['approved_by'] ?? null), "{$label} belum memiliki identitas penyetuju.");
        $flag(! $this->filled($master['approved_at'] ?? null), "{$label} belum memiliki waktu persetujuan.");
        $flag(! $this->validChecksum($master['checksum'] ?? null), "Checksum {$label} tidak valid.");
        $flag(($master['integrity_valid'] ?? false) !== true, "Integritas isi {$label} tidak sesuai dengan checksum persetujuan.");
        $flag(($master['is_effective'] ?? false) !== true, "{$label} belum atau tidak lagi berlaku pada tanggal penawaran.");

        if ($requiresActiveTemplate) {
            $flag(($master['template_active'] ?? false) !== true, 'Template penawaran tidak aktif.');
            $flag(($master['schema_valid'] ?? false) !== true, 'Schema template penawaran resmi tidak valid atau tidak lengkap.');
        }
    }

    private function validChecksum(mixed $checksum): bool
    {
        return is_string($checksum) && preg_match('/\A[a-f0-9]{64}\z/i', $checksum) === 1;
    }
}
