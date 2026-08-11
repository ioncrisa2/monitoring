<?php

namespace Tests\Feature;

use App\Services\Offers\OfferDocumentRenderer;
use InvalidArgumentException;
use Tests\TestCase;

class OfferDocumentRendererTest extends TestCase
{
    public function test_it_renders_a_safe_draft_html_contract_with_all_clauses_in_order(): void
    {
        $snapshot = $this->draftSnapshot();
        $snapshot['recipient']['name'] = 'PT Contoh <script>alert("x")</script>';

        $html = app(OfferDocumentRenderer::class)->renderHtml($snapshot);

        $this->assertStringContainsString('DRAF — BELUM DISETUJUI', $html);
        $this->assertStringContainsString('@page :odd', $html);
        $this->assertStringContainsString('@page :even', $html);
        $this->assertSame(25, substr_count($html, 'data-clause-key='));
        $this->assertStringContainsString('PT Contoh &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringNotContainsString('https://', $html);

        $lastPosition = -1;

        foreach (config('offer-documents.clause_titles') as $key => $title) {
            $position = strpos($html, 'data-clause-key="'.$key.'"');

            $this->assertNotFalse($position, "Klausul {$title} tidak ditemukan.");
            $this->assertGreaterThan($lastPosition, $position, "Urutan klausul {$title} tidak sesuai.");
            $this->assertStringContainsString($title, substr($html, $position, 1_500));
            $lastPosition = $position;
        }
    }

    public function test_it_renders_a_valid_a4_pdf_without_external_tooling(): void
    {
        $pdf = app(OfferDocumentRenderer::class)->render($this->draftSnapshot());

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(10_000, strlen($pdf));
        $this->assertMatchesRegularExpression(
            '/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+595\.28\d*\s+841\.89\d*\s*\]/',
            $pdf,
        );

        preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pages);

        $this->assertGreaterThanOrEqual(2, count($pages[0]));
    }

    public function test_it_rejects_a_snapshot_without_the_complete_ordered_clause_set(): void
    {
        $snapshot = $this->draftSnapshot();
        array_pop($snapshot['clauses']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tepat 25 klausul terurut');

        app(OfferDocumentRenderer::class)->renderHtml($snapshot);
    }

    public function test_provisional_copy_is_complete_and_clearly_marked_as_draft(): void
    {
        $titles = config('offer-documents.clause_titles');
        $provisional = config('offer-documents.provisional');

        $this->assertSame(array_keys($titles), array_keys($provisional['clause_paragraphs']));
        $this->assertStringContainsString('DRAF', $provisional['issuer']['name']);
        $this->assertStringContainsString('DRAF', $provisional['opening']);
        $this->assertStringContainsString('DRAF', $provisional['closing']);

        foreach ($provisional['clause_paragraphs'] as $paragraph) {
            $this->assertStringContainsString('DRAF', $paragraph);
        }
    }

    private function draftSnapshot(): array
    {
        $clauses = [];

        foreach (config('offer-documents.clause_titles') as $key => $title) {
            $number = count($clauses) + 1;
            $paragraphs = ["Draf isi klausul {$number} untuk vertical slice. Redaksi final menunggu persetujuan legal dan operasional."];
            $items = [];

            if ($key === 'professional_fee') {
                $paragraphs = [
                    'Biaya jasa penilaian dalam draf ini sebesar Rp10.000.000 dan belum menjadi penawaran final.',
                    'Perhitungan pajak serta komponen biaya akan mengikuti snapshot komersial yang telah disetujui.',
                ];
            }

            if ($key === 'initial_data_request') {
                $paragraphs = [];
                $items = [
                    'Dokumen legal objek penilaian.',
                    'Data teknis dan informasi pendukung yang dapat diverifikasi.',
                ];
            }

            $clauses[] = [
                'number' => $number,
                'key' => $key,
                'title' => $title,
                'paragraphs' => $paragraphs,
                'items' => $items,
            ];
        }

        return [
            'document' => [
                'number' => "DRAF/001/S.Kontrak/KJPP-HJA'R/VIII/2026",
                'place' => 'Jakarta',
                'date' => '12 Agustus 2026',
                'subject' => 'Penawaran Jasa Penilaian',
                'opening' => 'Sehubungan dengan permintaan jasa penilaian yang kami terima, bersama ini kami sampaikan draf ketentuan penugasan sebagai bahan peninjauan internal.',
                'closing' => 'Demikian draf penawaran ini disampaikan untuk ditinjau. Dokumen ini belum merupakan penawaran final dan belum dapat digunakan sebagai dasar penugasan.',
            ],
            'issuer' => [
                'name' => 'KJPP Hendrawan, Joni & Rekan',
                'address_lines' => ['Gedung Contoh, Jalan Uji No. 1, Jakarta'],
                'contact_lines' => ['Telepon (021) 000000', 'email@example.test'],
            ],
            'recipient' => [
                'name' => 'PT Klien Contoh',
                'attention' => 'Direksi',
                'address_lines' => ['Jalan Pemberi Tugas No. 10', 'Jakarta'],
            ],
            'clauses' => $clauses,
            'signatures' => [
                'issuer_name' => 'Penandatangan Contoh',
                'issuer_title' => 'Rekan',
                'client_name' => 'PT Klien Contoh',
                'client_title' => 'Direktur',
            ],
        ];
    }
}
