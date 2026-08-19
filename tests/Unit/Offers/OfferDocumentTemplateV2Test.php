<?php

namespace Tests\Unit\Offers;

use App\Enums\OfferDocumentMasterReviewStatus;
use App\Enums\OfferTemplateBlockType;
use App\Enums\OfferTemplateCategory;
use App\Models\Branch;
use App\Models\IssuerProfileVersion;
use App\Models\OfferTemplate;
use App\Models\OfferTemplateVersion;
use App\Models\User;
use App\Services\Offers\OfferDocumentMasterApprovalService;
use App\Services\Offers\OfferDocumentMasterIntegrityService;
use Database\Seeders\OfferDocumentTemplateSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferDocumentTemplateV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_v2_schemas_are_safe_complete_and_cover_supported_blocks(): void
    {
        $this->seed(OfferDocumentTemplateSeeder::class);
        $integrity = app(OfferDocumentMasterIntegrityService::class);
        $blockTypes = [];

        $this->assertSame(3, OfferTemplate::query()->count());
        $this->assertSame(3, OfferTemplateVersion::query()->count());

        foreach (OfferTemplateVersion::query()->with('template')->get() as $version) {
            $this->assertSame(2, $version->schema_version);
            $this->assertSame('offer-a4-v2', $version->layout_version);
            $this->assertSame('all_pages', $version->header_mode);
            $this->assertSame(OfferDocumentMasterReviewStatus::Draft->value, $version->status);
            $this->assertNull($version->approved_by);
            $this->assertCount(25, $version->clause_schema['clauses']);
            $this->assertSame([], $integrity->templateSchemaErrorsFor($version));

            foreach ($version->clause_schema['clauses'] as $clause) {
                foreach ($clause['blocks'] as $block) {
                    $blockTypes[] = $block['type'];
                }
            }
        }

        sort($blockTypes);
        $expected = array_column(OfferTemplateBlockType::cases(), 'value');
        sort($expected);
        $this->assertSame($expected, array_values(array_unique($blockTypes)));
        $this->assertSame(
            array_column(OfferTemplateCategory::cases(), 'value'),
            OfferTemplate::query()->orderBy('id')->get()->map->category->map->value->all(),
        );
    }

    public function test_v2_schema_rejects_missing_clause_unknown_block_token_and_executable_markup(): void
    {
        $this->seed(OfferDocumentTemplateSeeder::class);
        $schema = OfferTemplateVersion::query()->firstOrFail()->clause_schema;
        unset($schema['clauses']['other_terms']);
        $schema['clauses']['closing']['blocks'][] = [
            'type' => 'run_php',
            'text' => '<?php echo {{secrets.password}}; ?><script>alert(1)</script>',
        ];

        $errors = app(OfferDocumentMasterIntegrityService::class)->templateSchemaErrors($schema, null, 2);
        $message = implode(' ', $errors);

        $this->assertStringContainsString('tepat 25 klausul', $message);
        $this->assertStringContainsString('type tidak dikenal', $message);

        $schema['clauses']['closing']['blocks'][0] = [
            'type' => 'text',
            'text' => '<?php echo {{secrets.password}}; ?><script>alert(1)</script>',
        ];
        $errors = app(OfferDocumentMasterIntegrityService::class)->templateSchemaErrors($schema, null, 2);
        $message = implode(' ', $errors);

        $this->assertStringContainsString('HTML, Blade, atau PHP', $message);
        $this->assertStringContainsString('token tidak dikenal', $message);
    }

    public function test_v2_review_requires_submission_and_an_independent_reviewer_then_is_immutable(): void
    {
        $this->seed(OfferDocumentTemplateSeeder::class);
        [$creator, $reviewer] = $this->users();
        $version = OfferTemplateVersion::query()->with('template')->firstOrFail();
        $version->update([
            'created_by' => $creator->getKey(),
            'effective_from' => '2026-08-19',
        ]);
        $approval = app(OfferDocumentMasterApprovalService::class);

        try {
            $approval->approve($version->fresh(), $reviewer);
            $this->fail('Draft v2 tidak boleh langsung disetujui.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('diajukan', $exception->getMessage());
        }

        $submitted = $approval->submit($version->fresh(), $creator);
        $this->assertSame('submitted', $submitted->status);
        $this->assertSame($creator->getKey(), $submitted->submitted_by);

        try {
            $approval->approve($submitted, $creator);
            $this->fail('Pengaju tidak boleh menyetujui master sendiri.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('tidak boleh mereview', $exception->getMessage());
        }

        $approved = $approval->approve($submitted->fresh(), $reviewer);
        $this->assertSame('approved', $approved->status);
        $this->assertSame($reviewer->getKey(), $approved->reviewed_by);
        $this->assertSame($reviewer->getKey(), $approved->approved_by);
        $this->assertTrue(app(OfferDocumentMasterIntegrityService::class)->verify($approved));

        try {
            $approved->template->update(['purpose' => 'Tujuan induk yang diubah']);
            $this->fail('Identitas induk template dengan versi approved seharusnya immutable.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('immutable');
        $approved->update(['effective_until' => '2027-01-01']);
    }

    public function test_rejection_records_reason_and_preserves_the_submitted_checksum(): void
    {
        $this->seed(OfferDocumentTemplateSeeder::class);
        [$creator, $reviewer] = $this->users();
        $version = OfferTemplateVersion::query()->with('template')->firstOrFail();
        $version->update(['created_by' => $creator->getKey(), 'effective_from' => '2026-08-19']);
        $approval = app(OfferDocumentMasterApprovalService::class);
        $submitted = $approval->submit($version, $creator);
        $checksum = $submitted->checksum;
        $rejected = $approval->reject($submitted, $reviewer, 'Redaksi perlu diperbaiki.');

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Redaksi perlu diperbaiki.', $rejected->rejection_note);
        $this->assertSame($reviewer->getKey(), $rejected->reviewed_by);
        $this->assertSame($checksum, $rejected->checksum);
        $this->assertTrue(app(OfferDocumentMasterIntegrityService::class)->verify($rejected));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('immutable');
        $rejected->update(['effective_from' => '2026-08-20']);
    }

    public function test_new_v1_approval_is_rejected_but_explicit_legacy_approval_remains_read_compatible(): void
    {
        [$actor] = $this->users();
        $clauses = [];

        foreach (array_keys((array) config('offer-documents.clause_titles')) as $key) {
            $clauses[$key] = ['paragraphs' => ['Redaksi legal lama yang masih didukung.']];
        }

        $template = OfferTemplate::create([
            'code' => 'legacy-standard',
            'name' => 'Legacy Standard',
            'active' => true,
        ]);
        $version = OfferTemplateVersion::create([
            'offer_template_id' => $template->getKey(),
            'version_no' => 1,
            'schema_version' => 1,
            'clause_schema' => [
                'document' => ['opening' => 'Pembuka lama.', 'closing' => 'Penutup lama.'],
                'clauses' => $clauses,
            ],
            'layout_version' => 'standard-v1',
            'header_mode' => 'odd_pages',
            'effective_from' => '2026-08-19',
        ]);

        try {
            app(OfferDocumentMasterApprovalService::class)->approve($version, $actor);
            $this->fail('Approval baru tidak boleh menggunakan schema v1.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('diajukan', $exception->getMessage());
        }

        $approved = app(OfferDocumentMasterApprovalService::class)->approveLegacy($version->fresh(), $actor);
        $this->assertSame('approved', $approved->status);
        app(OfferDocumentMasterIntegrityService::class)->assertApprovedIntegrity($approved);
        $this->assertTrue(app(OfferDocumentMasterIntegrityService::class)->verify($approved));
    }

    public function test_seeder_is_idempotent_and_does_not_overwrite_an_edited_draft(): void
    {
        $this->seed(OfferDocumentTemplateSeeder::class);
        $version = OfferTemplateVersion::query()->with('template')->firstOrFail();
        $schema = $version->clause_schema;
        $schema['document']['opening'] = 'Redaksi hasil edit Sysadmin.';
        $version->update(['clause_schema' => $schema]);

        $this->seed(OfferDocumentTemplateSeeder::class);

        $this->assertSame(3, OfferTemplate::query()->count());
        $this->assertSame(3, OfferTemplateVersion::query()->count());
        $this->assertSame('Redaksi hasil edit Sysadmin.', $version->fresh()->clause_schema['document']['opening']);
        $this->assertSame(0, OfferTemplateVersion::query()->where('status', 'approved')->count());
    }

    public function test_new_issuer_approval_verifies_private_letterhead_bytes_and_detects_tampering(): void
    {
        [$creator, $reviewer] = $this->users();
        $root = storage_path('framework/testing/offer-master-'.bin2hex(random_bytes(6)));
        $relativePath = 'letterheads/header.png';
        $absolutePath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $bytes = $this->pngFixture(300, 50);
        mkdir(dirname($absolutePath), 0777, true);
        file_put_contents($absolutePath, $bytes);
        config()->set('offer-documents.renderer.approved_asset_path', $root);

        try {
            $issuer = IssuerProfileVersion::create([
                'branch_id' => $creator->branch_id,
                'version_no' => 1,
                'legal_name' => 'Kantor Jasa Penilai Publik',
                'address' => 'Alamat kantor resmi',
                'city' => 'Jakarta',
                'letterhead_path' => $relativePath,
                'letterhead_sha256' => hash('sha256', $bytes),
                'letterhead_mime' => 'image/png',
                'letterhead_width_px' => 300,
                'letterhead_height_px' => 50,
                'letterhead_size_bytes' => strlen($bytes),
                'effective_from' => '2026-08-19',
                'created_by' => $creator->getKey(),
            ]);
            $approval = app(OfferDocumentMasterApprovalService::class);
            $submitted = $approval->submit($issuer, $creator);
            $approved = $approval->approve($submitted, $reviewer);
            app(OfferDocumentMasterIntegrityService::class)->assertApprovedIntegrity($approved);

            file_put_contents($absolutePath, $bytes.'tampered');

            try {
                app(OfferDocumentMasterIntegrityService::class)->assertApprovedIntegrity($approved->fresh());
                $this->fail('Perubahan bytes letterhead seharusnya terdeteksi.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('Ukuran file letterhead', $exception->getMessage());
            }
        } finally {
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }

            if (is_dir(dirname($absolutePath))) {
                rmdir(dirname($absolutePath));
            }

            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }

    /** @return array{User, User} */
    private function users(): array
    {
        $branch = Branch::create([
            'code' => 'PST',
            'number_code' => 0,
            'name' => 'Kantor Pusat',
            'active' => true,
        ]);

        return [
            User::create([
                'branch_id' => $branch->getKey(),
                'name' => 'Pembuat Master',
                'email' => 'master@example.test',
                'password' => 'password',
                'role' => 'sysadmin',
                'active' => true,
            ]),
            User::create([
                'branch_id' => $branch->getKey(),
                'name' => 'Reviewer Master',
                'email' => 'reviewer@example.test',
                'password' => 'password',
                'role' => 'supervisor',
                'active' => true,
            ]),
        ];
    }

    private function pngFixture(int $width, int $height): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };
        $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $row = "\x00".str_repeat("\xff\xff\xff", $width);

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', $header)
            .$chunk('IDAT', gzcompress(str_repeat($row, $height), 9))
            .$chunk('IEND', '');
    }
}
