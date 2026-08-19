<?php

declare(strict_types=1);

use App\Enums\OfferDocumentOutputMode;
use App\Services\Offers\OfferDocumentRenderer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Support\Offers\AnonymousOfferVisualFixtureFactory;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$arguments = array_slice($argv, 1);
$update = in_array('--update', $arguments, true);
$configuredBinary = null;

foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--pdftoppm=')) {
        $configuredBinary = substr($argument, strlen('--pdftoppm='));
    }
}

$pdftoppm = $configuredBinary
    ?: (getenv('PDFTOPPM_BIN') ?: null)
    ?: (new ExecutableFinder)->find('pdftoppm');

if (! is_string($pdftoppm) || $pdftoppm === '' || ! is_file($pdftoppm)) {
    fwrite(STDERR, "pdftoppm tidak ditemukan. Instal Poppler atau set PDFTOPPM_BIN/--pdftoppm.\n");
    exit(2);
}

if (! extension_loaded('gd')) {
    fwrite(STDERR, "Ekstensi PHP GD diperlukan untuk membandingkan golden PNG.\n");
    exit(2);
}

$root = dirname(__DIR__);
$workingRoot = storage_path('framework/testing/offer-visual-regression');
$assetRoot = $workingRoot.DIRECTORY_SEPARATOR.'assets';
$currentRoot = $workingRoot.DIRECTORY_SEPARATOR.'current';
$baselineRoot = $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Visual'.DIRECTORY_SEPARATOR.'Offers'.DIRECTORY_SEPARATOR.'baselines';

File::deleteDirectory($workingRoot);
File::ensureDirectoryExists($assetRoot);
File::ensureDirectoryExists($currentRoot);
File::ensureDirectoryExists($baselineRoot);
config()->set('offer-documents.renderer.approved_asset_path', $assetRoot);

$letterheadPath = $assetRoot.DIRECTORY_SEPARATOR.'anonymous-letterhead.png';
$letterhead = imagecreatetruecolor(1200, 150);
$white = imagecolorallocate($letterhead, 255, 255, 255);
$ink = imagecolorallocate($letterhead, 28, 45, 64);
imagefilledrectangle($letterhead, 0, 0, 1199, 149, $white);
imagefilledrectangle($letterhead, 0, 126, 1199, 134, $ink);
imagestring($letterhead, 5, 28, 30, 'KOP RESMI - FIXTURE ANONIM', $ink);
imagestring($letterhead, 3, 28, 72, 'Hanya untuk pengujian visual otomatis', $ink);
imagepng($letterhead, $letterheadPath);
imagedestroy($letterhead);

$failures = [];
$report = [];

foreach (AnonymousOfferVisualFixtureFactory::manifest() as $key => $definition) {
    $fixtureDirectory = $currentRoot.DIRECTORY_SEPARATOR.$key;
    $baselineDirectory = $baselineRoot.DIRECTORY_SEPARATOR.$key;
    File::ensureDirectoryExists($fixtureDirectory);

    $snapshot = AnonymousOfferVisualFixtureFactory::make($key, [
        'path' => basename($letterheadPath),
        'mime' => 'image/png',
        'sha256' => hash_file('sha256', $letterheadPath),
    ]);
    $pdf = app(OfferDocumentRenderer::class)->render($snapshot, OfferDocumentOutputMode::PrintReady);
    $pdfPath = $fixtureDirectory.DIRECTORY_SEPARATOR.'document.pdf';
    File::put($pdfPath, $pdf);

    preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pageObjects);

    if (count($pageObjects[0]) !== $definition['expected_pages']) {
        $failures[] = "{$key}: PDF menghasilkan ".count($pageObjects[0])." halaman, seharusnya {$definition['expected_pages']}.";

        continue;
    }

    $prefix = $fixtureDirectory.DIRECTORY_SEPARATOR.'page';
    $process = new Process([
        $pdftoppm,
        '-png',
        '-r',
        '144',
        '-f',
        '1',
        '-l',
        (string) $definition['expected_pages'],
        $pdfPath,
        $prefix,
    ], $root, timeout: 120);
    $process->run();

    if (! $process->isSuccessful()) {
        $failures[] = "{$key}: rasterisasi gagal: ".trim($process->getErrorOutput() ?: $process->getOutput());

        continue;
    }

    $pages = glob($prefix.'-*.png') ?: [];
    natsort($pages);
    $pages = array_values($pages);

    if (count($pages) !== $definition['expected_pages']) {
        $failures[] = "{$key}: menghasilkan ".count($pages)." PNG, seharusnya {$definition['expected_pages']}.";

        continue;
    }

    if ($update) {
        File::deleteDirectory($baselineDirectory);
        File::ensureDirectoryExists($baselineDirectory);
    } else {
        $baselineManifestPath = $baselineDirectory.DIRECTORY_SEPARATOR.'manifest.json';
        $baselinePages = glob($baselineDirectory.DIRECTORY_SEPARATOR.'page-*.png') ?: [];

        if (! is_file($baselineManifestPath)) {
            $failures[] = "{$key}: manifest baseline belum ada; jalankan composer update:offer-visual setelah inspeksi manual.";
        } else {
            $baselineManifest = json_decode((string) File::get($baselineManifestPath), true);
            $expectedManifest = [
                'fixture' => $key,
                'category' => $definition['category'],
                'pages' => $definition['expected_pages'],
                'dpi' => 144,
                'pixel_size' => [1191, 1684],
                'renderer_version' => config('offer-documents.renderer.version'),
                'contains_customer_data' => false,
            ];

            if (! is_array($baselineManifest)) {
                $failures[] = "{$key}: manifest baseline bukan JSON object yang valid.";
            } else {
                foreach ($expectedManifest as $manifestKey => $expectedValue) {
                    if (($baselineManifest[$manifestKey] ?? null) !== $expectedValue) {
                        $failures[] = "{$key}: metadata baseline {$manifestKey} tidak sesuai kontrak saat ini.";
                    }
                }
            }
        }

        if (count($baselinePages) !== $definition['expected_pages']) {
            $failures[] = "{$key}: baseline memiliki ".count($baselinePages)." PNG, seharusnya {$definition['expected_pages']}.";
        }
    }

    $fixtureDiffs = [];

    foreach ($pages as $index => $pagePath) {
        $pageNumber = $index + 1;
        $dimensions = getimagesize($pagePath);

        if (! is_array($dimensions) || $dimensions[0] !== 1191 || $dimensions[1] !== 1684) {
            $actual = is_array($dimensions) ? "{$dimensions[0]}x{$dimensions[1]}" : 'tidak terbaca';
            $failures[] = "{$key} halaman {$pageNumber}: raster {$actual}, seharusnya 1191x1684 (A4 @ 144 DPI).";

            continue;
        }

        $baselinePath = $baselineDirectory.DIRECTORY_SEPARATOR.'page-'.$pageNumber.'.png';

        if ($update) {
            File::copy($pagePath, $baselinePath);

            continue;
        }

        if (! is_file($baselinePath)) {
            $failures[] = "{$key} halaman {$pageNumber}: baseline belum ada; jalankan composer update:offer-visual setelah inspeksi manual.";

            continue;
        }

        $diff = comparePng($baselinePath, $pagePath);
        $fixtureDiffs[] = $diff;

        if ($diff['mean_channel_delta'] > 0.003 || $diff['changed_pixel_ratio'] > 0.02) {
            $failures[] = sprintf(
                '%s halaman %d: beda visual terlalu besar (mean %.5f, changed %.3f%%).',
                $key,
                $pageNumber,
                $diff['mean_channel_delta'],
                $diff['changed_pixel_ratio'] * 100,
            );
        }
    }

    if ($update) {
        File::put($baselineDirectory.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'fixture' => $key,
            'label' => $definition['label'],
            'reference_shape' => $definition['reference_shape'],
            'category' => $definition['category'],
            'pages' => $definition['expected_pages'],
            'dpi' => 144,
            'pixel_size' => [1191, 1684],
            'renderer_version' => config('offer-documents.renderer.version'),
            'contains_customer_data' => false,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
    }

    $report[] = [
        'fixture' => $key,
        'pages' => count($pages),
        'mode' => $update ? 'updated' : 'checked',
        'maximum_mean_delta' => $fixtureDiffs === [] ? null : max(array_column($fixtureDiffs, 'mean_channel_delta')),
        'maximum_changed_ratio' => $fixtureDiffs === [] ? null : max(array_column($fixtureDiffs, 'changed_pixel_ratio')),
    ];
}

foreach ($report as $row) {
    $suffix = $row['maximum_mean_delta'] === null
        ? ''
        : sprintf('; mean maks %.5f; piksel berubah maks %.3f%%', $row['maximum_mean_delta'], $row['maximum_changed_ratio'] * 100);
    fwrite(STDOUT, "{$row['fixture']}: {$row['pages']} halaman {$row['mode']}{$suffix}\n");
}

if ($failures !== []) {
    fwrite(STDERR, "\nVisual regression gagal:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

fwrite(STDOUT, $update
    ? "\nBaseline 144 DPI diperbarui. Tinjau seluruh PNG sebelum commit.\n"
    : "\nSemua golden visual 144 DPI sesuai baseline.\n");

/**
 * Compare every fourth pixel. The threshold tolerates minor rasterizer
 * antialiasing differences while still catching moved, clipped, or missing
 * tables, repeating headers, letterheads, and signature blocks.
 *
 * @return array{mean_channel_delta: float, changed_pixel_ratio: float}
 */
function comparePng(string $baselinePath, string $currentPath): array
{
    $baseline = imagecreatefrompng($baselinePath);
    $current = imagecreatefrompng($currentPath);

    if ($baseline === false || $current === false) {
        throw new RuntimeException('Golden PNG tidak dapat dibaca oleh GD.');
    }

    $width = imagesx($baseline);
    $height = imagesy($baseline);

    if ($width !== imagesx($current) || $height !== imagesy($current)) {
        imagedestroy($baseline);
        imagedestroy($current);

        return ['mean_channel_delta' => 1.0, 'changed_pixel_ratio' => 1.0];
    }

    $samples = 0;
    $changed = 0;
    $absoluteDelta = 0;

    for ($y = 0; $y < $height; $y += 4) {
        for ($x = 0; $x < $width; $x += 4) {
            $left = imagecolorat($baseline, $x, $y);
            $right = imagecolorat($current, $x, $y);
            $red = abs((($left >> 16) & 0xFF) - (($right >> 16) & 0xFF));
            $green = abs((($left >> 8) & 0xFF) - (($right >> 8) & 0xFF));
            $blue = abs(($left & 0xFF) - ($right & 0xFF));

            $absoluteDelta += $red + $green + $blue;
            $changed += max($red, $green, $blue) > 24 ? 1 : 0;
            $samples++;
        }
    }

    imagedestroy($baseline);
    imagedestroy($current);

    return [
        'mean_channel_delta' => $absoluteDelta / max(1, $samples * 3 * 255),
        'changed_pixel_ratio' => $changed / max(1, $samples),
    ];
}
