<?php

namespace Tests\Feature;

use App\Enums\OfferDocumentOutputMode;
use App\Services\Offers\OfferDocumentRenderer;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Support\Offers\AnonymousOfferVisualFixtureFactory;
use Tests\TestCase;

class OfferDocumentVisualContractTest extends TestCase
{
    private string $assetDirectory;

    private string $letterheadPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assetDirectory = storage_path('framework/testing/offer-visual-contract-assets');
        File::ensureDirectoryExists($this->assetDirectory);
        config()->set('offer-documents.renderer.approved_asset_path', $this->assetDirectory);

        $this->letterheadPath = $this->assetDirectory.DIRECTORY_SEPARATOR.'anonymous-letterhead.png';
        $this->writeAnonymousLetterhead($this->letterheadPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->assetDirectory);

        parent::tearDown();
    }

    public function test_anonymous_reference_fixtures_render_the_expected_a4_page_shapes(): void
    {
        $expectedShapes = [
            'collateral-multi' => 5,
            'collateral-detailed' => 6,
            'auction-twelve-assets' => 9,
            'rental-market' => 5,
        ];

        $this->assertSame($expectedShapes, array_map(
            static fn (array $definition): int => $definition['expected_pages'],
            AnonymousOfferVisualFixtureFactory::manifest(),
        ));

        $actualShapes = [];

        foreach (array_keys($expectedShapes) as $fixtureKey) {
            $snapshot = $this->snapshot($fixtureKey);
            $pdf = app(OfferDocumentRenderer::class)->render(
                $snapshot,
                OfferDocumentOutputMode::PrintReady,
            );

            preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pageObjects);
            preg_match_all(
                '/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+595\.28\d*\s+841\.89\d*\s*\]/',
                $pdf,
                $a4MediaBoxes,
            );
            preg_match_all('/\/MediaBox\s*\[[^\]]+\]/', $pdf, $allMediaBoxes);

            $actualShapes[$fixtureKey] = count($pageObjects[0]);
            $this->assertNotEmpty($allMediaBoxes[0]);
            $this->assertSame(
                count($allMediaBoxes[0]),
                count($a4MediaBoxes[0]),
                "Fixture {$fixtureKey} mengandung MediaBox selain A4 portrait.",
            );
            $this->assertStringNotContainsString('/Type /Sig', $pdf);
            $this->assertStringNotContainsString('/SigFlags', $pdf);
            $this->assertStringNotContainsString('/AcroForm', $pdf);
        }

        $this->assertSame(
            $expectedShapes,
            $actualShapes,
            'Jumlah halaman fixture berubah; lakukan inspeksi visual sebelum menerima baseline baru.',
        );
    }

    public function test_visual_layout_contract_repeats_letterhead_and_protects_tables_and_wet_ink_block(): void
    {
        $snapshot = $this->snapshot('auction-twelve-assets');
        $html = app(OfferDocumentRenderer::class)->renderHtml(
            $snapshot,
            OfferDocumentOutputMode::PrintReady,
        );

        $this->assertMatchesRegularExpression('/@page\s*\{[^}]*size:\s*A4 portrait;/s', $html);
        $this->assertMatchesRegularExpression('/\.document-letterhead\s*\{[^}]*position:\s*fixed;/s', $html);
        $this->assertSame(1, substr_count($html, 'class="document-letterhead"'));
        $this->assertSame(1, substr_count($html, 'class="letterhead-image"'));

        $this->assertMatchesRegularExpression('/\.data-table thead\s*\{[^}]*display:\s*table-header-group;/s', $html);
        $this->assertMatchesRegularExpression('/\.data-table tr\s*\{[^}]*page-break-inside:\s*avoid;/s', $html);
        $this->assertMatchesRegularExpression('/\.signatures\s*\{[^}]*page-break-inside:\s*avoid;/s', $html);
        $this->assertStringContainsString('data-signing-mode="wet-ink"', $html);
        $this->assertStringContainsString('class="signature-space"', $html);

        foreach (['asset-table', 'fee-table', 'payment-table', 'requirements-table', 'exposure-table'] as $tableClass) {
            $this->assertMatchesRegularExpression(
                '/<table class="data-table [^"]*'.preg_quote($tableClass, '/').'[^"]*">\s*<thead>/s',
                $html,
                "Tabel {$tableClass} harus mempunyai thead agar header dapat diulang saat tabel melintas halaman.",
            );
        }

        foreach (['<footer', 'class="footer', 'Halaman 1', 'page-number', 'counter(page)', 'counter(pages)'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_visual_manifest_contains_only_anonymous_data_and_144_dpi_contract(): void
    {
        foreach (AnonymousOfferVisualFixtureFactory::manifest() as $key => $definition) {
            $snapshot = $this->snapshot($key);
            $serialized = json_encode($snapshot, JSON_THROW_ON_ERROR);

            $this->assertSame(144, $snapshot['metadata']['fixture']['raster_dpi']);
            $this->assertFalse($snapshot['metadata']['fixture']['contains_customer_data']);
            $this->assertCount($definition['asset_count'], collect($snapshot['clauses'])
                ->firstWhere('key', 'valuation_object')['blocks'][0]['rows']);

            foreach (['Caraka', 'Bank Index', 'Mandiri', 'Eka Sari', 'Setiadi Tanto', 'Hengki Setiawan'] as $customerMarker) {
                $this->assertStringNotContainsString($customerMarker, $serialized);
            }
        }
    }

    public function test_opt_in_144_dpi_golden_png_regression(): void
    {
        if (getenv('OFFER_VISUAL_REGRESSION') !== '1') {
            $this->markTestSkipped('Set OFFER_VISUAL_REGRESSION=1 untuk menjalankan pembandingan golden PNG 144 DPI.');
        }

        $process = new Process(
            [PHP_BINARY, base_path('tools/offer-visual-regression.php')],
            base_path(),
            timeout: 300,
        );
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            trim($process->getErrorOutput().PHP_EOL.$process->getOutput()),
        );
        $this->assertStringContainsString('Semua golden visual 144 DPI sesuai baseline.', $process->getOutput());
    }

    /** @return array<string, mixed> */
    private function snapshot(string $fixtureKey): array
    {
        return AnonymousOfferVisualFixtureFactory::make($fixtureKey, [
            'path' => basename($this->letterheadPath),
            'mime' => 'image/png',
            'sha256' => hash_file('sha256', $this->letterheadPath),
        ]);
    }

    private function writeAnonymousLetterhead(string $path): void
    {
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(1200, 150);
            $white = imagecolorallocate($image, 255, 255, 255);
            $ink = imagecolorallocate($image, 28, 45, 64);
            imagefilledrectangle($image, 0, 0, 1199, 149, $white);
            imagefilledrectangle($image, 0, 126, 1199, 134, $ink);
            imagestring($image, 5, 28, 30, 'KOP RESMI - FIXTURE ANONIM', $ink);
            imagestring($image, 3, 28, 72, 'Hanya untuk pengujian visual otomatis', $ink);
            imagepng($image, $path);
            imagedestroy($image);

            return;
        }

        File::put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));
    }
}
