<?php

namespace Tests\Unit\Offers;

use App\Enums\OfferNumberAllocationStatus;
use App\Models\Branch;
use App\Models\Debtor;
use App\Models\Offer;
use App\Models\Organization;
use App\Models\User;
use App\Services\Offers\OfferNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferNumberAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adopts_a_legacy_number_idempotently_without_incrementing_it(): void
    {
        [$offer, $user] = $this->offer(sequence: 190, number: "190.A/S.Kontrak/KJPP-HJA'R/10/VIII/2026");
        $allocator = app(OfferNumberAllocator::class);

        $first = $allocator->adoptExisting($offer, $user);
        $second = $allocator->adoptExisting($offer->fresh(), $user);

        $this->assertTrue($first->is($second));
        $this->assertSame(190, $first->sequence_no);
        $this->assertSame('A', $first->number_suffix);
        $this->assertSame(OfferNumberAllocationStatus::Allocated, $first->status);
        $this->assertSame($first->getKey(), $offer->fresh()->current_number_allocation_id);
        $this->assertDatabaseCount('offer_number_allocations', 1);
        $this->assertDatabaseHas('offer_number_counters', [
            'scope_key' => 'global',
            'sequence_year' => 2026,
            'last_sequence' => 190,
        ]);
    }

    public function test_it_allocates_the_next_number_after_legacy_rows_and_supports_suffix(): void
    {
        [$legacy, $user, $branch, $debtor, $client] = $this->offer(
            sequence: 7,
            number: "7/S.Kontrak/KJPP-HJA'R/10/VIII/2026",
        );
        $placeholder = Offer::create([
            'offer_no' => 'draft-placeholder-2',
            'sequence_no' => null,
            'offer_date' => '2026-08-12',
            'branch_id' => $branch->getKey(),
            'debtor_id' => $debtor->getKey(),
            'client_id' => $client->getKey(),
            'fee' => 0,
            'created_by' => $user->getKey(),
        ]);

        $allocation = app(OfferNumberAllocator::class)->allocate($placeholder, $user, '.B');

        $this->assertSame(8, $allocation->sequence_no);
        $this->assertSame('B', $allocation->number_suffix);
        $this->assertSame("8.B/S.Kontrak/KJPP-HJA'R/10/VIII/2026", $allocation->full_number);
        $this->assertSame($allocation->full_number, $placeholder->fresh()->offer_no);
        $this->assertSame(2026, $placeholder->fresh()->sequence_year);
        $this->assertDatabaseCount('offer_number_allocations', 1);
        $this->assertNotNull($legacy->fresh());
    }

    /** @return array{Offer, User, Branch, Debtor, Organization} */
    private function offer(int $sequence, string $number): array
    {
        $branch = Branch::create([
            'code' => 'JKT',
            'number_code' => 10,
            'name' => 'Jakarta',
            'active' => true,
        ]);
        $debtor = Debtor::create(['name' => 'PT Debitur']);
        $client = Organization::create(['name' => 'PT Klien', 'type' => 'pemberi_tugas']);
        $user = User::create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => 'password',
            'role' => 'admin',
            'active' => true,
        ]);
        $offer = Offer::create([
            'offer_no' => $number,
            'sequence_no' => $sequence,
            'offer_date' => '2026-08-12',
            'branch_id' => $branch->getKey(),
            'debtor_id' => $debtor->getKey(),
            'client_id' => $client->getKey(),
            'fee' => 1_000_000,
            'created_by' => $user->getKey(),
        ]);

        return [$offer, $user, $branch, $debtor, $client];
    }
}
