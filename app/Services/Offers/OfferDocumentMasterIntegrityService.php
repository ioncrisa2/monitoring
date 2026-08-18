<?php

namespace App\Services\Offers;

use App\Models\DocumentSignerVersion;
use App\Models\IssuerProfileVersion;
use App\Models\OfferTemplateVersion;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use JsonException;

final class OfferDocumentMasterIntegrityService
{
    /**
     * Return a deterministic checksum of fields that define an approved master.
     * Approval metadata, status, primary keys, and timestamps are intentionally
     * excluded because they describe the approval event rather than its content.
     */
    public function checksum(Model $master): string
    {
        try {
            return hash('sha256', json_encode(
                $this->canonicalize($this->contentPayload($master)),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            ));
        } catch (JsonException $exception) {
            throw new DomainException('Konten master dokumen tidak dapat dibuatkan checksum.', previous: $exception);
        }
    }

    public function verify(Model $master): bool
    {
        $stored = $master->getAttribute('checksum');

        return is_string($stored)
            && preg_match('/\A[a-f0-9]{64}\z/i', $stored) === 1
            && hash_equals(mb_strtolower($stored), $this->checksum($master));
    }

    public function assertApprovedIntegrity(Model $master): void
    {
        $this->assertSupported($master);

        if ($master->getAttribute('status') !== 'approved'
            || empty($master->getAttribute('approved_by'))
            || empty($master->getAttribute('approved_at'))) {
            throw new DomainException('Master approved wajib memiliki penyetuju dan waktu persetujuan.');
        }

        $this->assertContentValid($master);

        if (! $this->verify($master)) {
            throw new DomainException('Checksum master approved tidak sesuai dengan kontennya.');
        }
    }

    public function assertContentValid(Model $master): void
    {
        $this->assertSupported($master);

        if ($master->effective_from === null) {
            throw new DomainException('Tanggal mulai berlaku master wajib diisi sebelum master disetujui.');
        }

        if ($master instanceof OfferTemplateVersion) {
            $errors = $this->templateSchemaErrors(
                (array) $master->clause_schema,
                is_array($master->condition_schema) ? $master->condition_schema : null,
            );

            if ($master->schema_version !== 1) {
                $errors[] = 'Versi schema template belum didukung.';
            }

            if ($master->layout_version !== 'standard-v1') {
                $errors[] = 'Versi layout template belum didukung.';
            }

            if (! in_array($master->header_mode, ['odd_pages', 'all_pages'], true)) {
                $errors[] = 'Mode header template tidak didukung.';
            }

            if ($errors !== []) {
                throw new DomainException('Schema template tidak valid: '.implode(' ', $errors));
            }

            return;
        }

        $requiredFields = match (true) {
            $master instanceof IssuerProfileVersion => [
                'legal_name' => 'Nama legal penerbit',
                'address' => 'Alamat penerbit',
                'city' => 'Kota penerbit',
            ],
            $master instanceof DocumentSignerVersion => [
                'signer_key' => 'Kode penandatangan',
                'full_name' => 'Nama penandatangan',
                'position' => 'Jabatan penandatangan',
            ],
            default => [],
        };

        foreach ($requiredFields as $field => $label) {
            if (! is_string($master->getAttribute($field)) || trim($master->getAttribute($field)) === '') {
                throw new DomainException("{$label} wajib diisi sebelum master disetujui.");
            }
        }

        if ($master->effective_from !== null
            && $master->effective_until !== null
            && $master->effective_until->lt($master->effective_from)) {
            throw new DomainException('Tanggal akhir berlaku master tidak boleh mendahului tanggal mulai berlaku.');
        }
    }

    /** @return list<string> */
    public function templateSchemaErrors(array $schema, ?array $conditionSchema = null): array
    {
        $errors = [];
        $document = $schema['document'] ?? null;
        $clauses = $schema['clauses'] ?? null;
        $expectedKeys = array_keys((array) config('offer-documents.clause_titles', []));

        if (count($expectedKeys) !== 25) {
            $errors[] = 'Konfigurasi aplikasi wajib mendefinisikan tepat 25 klausul.';
        }

        $unknownRootKeys = array_values(array_diff(array_keys($schema), ['document', 'clauses']));

        if ($unknownRootKeys !== []) {
            $errors[] = 'Field schema tidak dikenal: '.implode(', ', $unknownRootKeys).'.';
        }

        if (! is_array($document)) {
            $errors[] = 'Bagian document wajib berupa object.';
        } else {
            $unknownDocumentKeys = array_values(array_diff(array_keys($document), ['opening', 'closing']));

            if ($unknownDocumentKeys !== []) {
                $errors[] = 'Field document tidak dikenal: '.implode(', ', $unknownDocumentKeys).'.';
            }

            foreach (['opening', 'closing'] as $field) {
                $value = $document[$field] ?? null;

                if (! is_string($value) || trim($value) === '') {
                    $errors[] = "document.{$field} wajib berupa teks yang terisi.";
                } elseif (mb_strlen($value) > 5000) {
                    $errors[] = "document.{$field} maksimal 5000 karakter.";
                }
            }
        }

        if ($conditionSchema !== null && $conditionSchema !== []) {
            $errors[] = 'condition_schema belum didukung oleh renderer dan harus kosong.';
        }

        if (! is_array($clauses)) {
            $errors[] = 'Bagian clauses wajib berupa object dengan tepat 25 klausul.';
        } else {
            $actualKeys = array_keys($clauses);
            $missing = array_values(array_diff($expectedKeys, $actualKeys));
            $unknown = array_values(array_diff($actualKeys, $expectedKeys));

            if ($missing !== []) {
                $errors[] = 'Klausul wajib belum tersedia: '.implode(', ', $missing).'.';
            }

            if ($unknown !== []) {
                $errors[] = 'Klausul tidak dikenal: '.implode(', ', $unknown).'.';
            }

            foreach ($expectedKeys as $key) {
                if (! array_key_exists($key, $clauses)) {
                    continue;
                }

                $this->validateClause($key, $clauses[$key], $errors);
            }
        }

        if (OfferDocumentContentGuard::containsProvisionalMarker($schema)) {
            $errors[] = 'Schema template approved tidak boleh memuat penanda DRAF atau provisional.';
        }

        return array_values(array_unique($errors));
    }

    /** @param list<string> $errors */
    private function validateClause(string $key, mixed $clause, array &$errors): void
    {
        if (! is_array($clause)) {
            $errors[] = "Klausul {$key} wajib berupa object.";

            return;
        }

        $unknownKeys = array_values(array_diff(array_keys($clause), ['paragraphs', 'items']));

        if ($unknownKeys !== []) {
            $errors[] = "Field klausul {$key} tidak dikenal: ".implode(', ', $unknownKeys).'.';
        }

        $paragraphs = $clause['paragraphs'] ?? [];
        $items = $clause['items'] ?? [];

        if (! is_array($paragraphs) || ! array_is_list($paragraphs)) {
            $errors[] = "Klausul {$key}.paragraphs wajib berupa list teks.";
            $paragraphs = [];
        }

        if (! is_array($items) || ! array_is_list($items)) {
            $errors[] = "Klausul {$key}.items wajib berupa list teks.";
            $items = [];
        }

        if ($paragraphs === [] && $items === []) {
            $errors[] = "Klausul {$key} wajib memiliki paragraph atau item.";
        }

        foreach (array_merge($paragraphs, $items) as $content) {
            if (! is_string($content) || trim($content) === '' || mb_strlen($content) > 2000) {
                $errors[] = "Konten klausul {$key} wajib berupa teks 1-2000 karakter.";
                break;
            }
        }
    }

    /** @return array<string, mixed> */
    private function contentPayload(Model $master): array
    {
        return match (true) {
            $master instanceof OfferTemplateVersion => [
                'type' => 'offer_template_version',
                'offer_template_id' => $master->offer_template_id,
                'version_no' => $master->version_no,
                'schema_version' => $master->schema_version,
                'clause_schema' => $master->clause_schema,
                'condition_schema' => $master->condition_schema,
                'layout_version' => $master->layout_version,
                'header_mode' => $master->header_mode,
                'effective_from' => $master->effective_from?->format('Y-m-d'),
            ],
            $master instanceof IssuerProfileVersion => [
                'type' => 'issuer_profile_version',
                'branch_id' => $master->branch_id,
                'version_no' => $master->version_no,
                'legal_name' => $master->legal_name,
                'permit_no' => $master->permit_no,
                'office_label' => $master->office_label,
                'address' => $master->address,
                'city' => $master->city,
                'phone' => $master->phone,
                'email' => $master->email,
                'letterhead_path' => $master->letterhead_path,
                'letterhead_sha256' => $master->letterhead_sha256,
                'letterhead_mime' => $master->letterhead_mime,
                'effective_from' => $master->effective_from?->format('Y-m-d'),
                'effective_until' => $master->effective_until?->format('Y-m-d'),
            ],
            $master instanceof DocumentSignerVersion => [
                'type' => 'document_signer_version',
                'branch_id' => $master->branch_id,
                'signer_key' => $master->signer_key,
                'version_no' => $master->version_no,
                'full_name' => $master->full_name,
                'title_suffix' => $master->title_suffix,
                'position' => $master->position,
                'permit_no' => $master->permit_no,
                'registration_no' => $master->registration_no,
                'phone' => $master->phone,
                'email' => $master->email,
                'effective_from' => $master->effective_from?->format('Y-m-d'),
                'effective_until' => $master->effective_until?->format('Y-m-d'),
            ],
            default => throw new DomainException('Jenis master dokumen tidak didukung.'),
        };
    }

    private function assertSupported(Model $master): void
    {
        if (! $master instanceof OfferTemplateVersion
            && ! $master instanceof IssuerProfileVersion
            && ! $master instanceof DocumentSignerVersion) {
            throw new DomainException('Jenis master dokumen tidak didukung.');
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
