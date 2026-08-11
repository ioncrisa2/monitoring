<?php

namespace App\Services\Offers;

use App\Enums\OfferNumberAllocationStatus;
use App\Enums\OfferWorkflowState;
use App\Models\Offer;
use App\Models\OfferNumberAllocation;
use App\Models\OfferNumberCounter;
use App\Models\User;
use App\Services\OfferNumberService;
use DomainException;
use Illuminate\Support\Facades\DB;

class OfferNumberAllocator
{
    public const GLOBAL_SCOPE = 'global';

    /**
     * Allocate a new official number. Authorization must be enforced by the caller.
     */
    public function allocate(
        Offer $offer,
        User $actor,
        string $numberSuffix = '',
        string $scopeKey = self::GLOBAL_SCOPE,
    ): OfferNumberAllocation {
        $scopeKey = $this->normalizeScopeKey($scopeKey);
        $numberSuffix = $this->normalizeSuffix($numberSuffix);

        return DB::transaction(function () use ($offer, $actor, $numberSuffix, $scopeKey) {
            $lockedOffer = Offer::query()
                ->with(['branch', 'currentNumberAllocation'])
                ->lockForUpdate()
                ->findOrFail($offer->getKey());

            $current = $lockedOffer->currentNumberAllocation;

            if ($current?->status === OfferNumberAllocationStatus::Allocated) {
                if ($current->number_suffix !== $numberSuffix || $current->scope_key !== $scopeKey) {
                    throw new DomainException('Penawaran sudah memiliki alokasi nomor aktif dengan scope atau suffix berbeda.');
                }

                return $current;
            }

            if ($lockedOffer->offer_date === null) {
                throw new DomainException('Tanggal penawaran wajib tersedia sebelum nomor dialokasikan.');
            }

            if ($lockedOffer->branch === null || $lockedOffer->branch->number_code === null) {
                throw new DomainException('Cabang penawaran belum memiliki kode angka untuk nomor surat.');
            }

            $year = (int) $lockedOffer->offer_date->year;
            $seed = max(
                (int) Offer::query()->whereYear('offer_date', $year)->max('sequence_no'),
                (int) OfferNumberAllocation::query()
                    ->where('scope_key', $scopeKey)
                    ->where('sequence_year', $year)
                    ->max('sequence_no'),
            );

            OfferNumberCounter::query()->insertOrIgnore([
                'scope_key' => $scopeKey,
                'sequence_year' => $year,
                'last_sequence' => $seed,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = OfferNumberCounter::query()
                ->where('scope_key', $scopeKey)
                ->where('sequence_year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            if ($counter->last_sequence < $seed) {
                $counter->forceFill(['last_sequence' => $seed])->save();
            }

            $counter->increment('last_sequence');
            $counter->refresh();

            $sequence = $counter->last_sequence;
            $baseNumber = OfferNumberService::build(
                $sequence,
                (int) $lockedOffer->branch->number_code,
                $lockedOffer->offer_date,
            );
            $fullNumber = $this->applySuffix($baseNumber, $sequence, $numberSuffix);

            $allocation = OfferNumberAllocation::query()->create([
                'offer_id' => $lockedOffer->getKey(),
                'branch_id' => $lockedOffer->branch_id,
                'scope_key' => $scopeKey,
                'sequence_year' => $year,
                'sequence_no' => $sequence,
                'number_suffix' => $numberSuffix,
                'format_snapshot' => [
                    'schema_version' => 1,
                    'pattern' => "{sequence}{suffix}/S.Kontrak/KJPP-HJA'R/{branch_code}/{roman_month}/{year}",
                    'branch_number_code' => (int) $lockedOffer->branch->number_code,
                    'branch_code' => $lockedOffer->branch->code,
                    'offer_date' => $lockedOffer->offer_date->format('Y-m-d'),
                ],
                'full_number' => $fullNumber,
                'status' => OfferNumberAllocationStatus::Allocated,
                'active_slot' => 1,
                'allocated_by' => $actor->getKey(),
                'allocated_at' => now(),
            ]);

            $lockedOffer->forceFill([
                'offer_no' => $fullNumber,
                'sequence_no' => $sequence,
                'sequence_year' => $year,
                'number_suffix' => $numberSuffix,
                'current_number_allocation_id' => $allocation->getKey(),
            ])->save();

            return $allocation;
        }, 5);
    }

    /**
     * Adopt the number already stored by the legacy Offer workflow without changing it.
     * Authorization must be enforced by the caller.
     */
    public function adoptExisting(
        Offer $offer,
        User $actor,
        string $scopeKey = self::GLOBAL_SCOPE,
    ): OfferNumberAllocation {
        $scopeKey = $this->normalizeScopeKey($scopeKey);

        return DB::transaction(function () use ($offer, $actor, $scopeKey) {
            $lockedOffer = Offer::query()
                ->with(['branch', 'currentNumberAllocation'])
                ->lockForUpdate()
                ->findOrFail($offer->getKey());

            if ($lockedOffer->currentNumberAllocation?->status === OfferNumberAllocationStatus::Allocated) {
                return $lockedOffer->currentNumberAllocation;
            }

            if ($lockedOffer->sequence_no === null || $lockedOffer->offer_date === null || trim($lockedOffer->offer_no) === '') {
                throw new DomainException('Nomor legacy belum lengkap dan tidak dapat diadopsi.');
            }

            if ($lockedOffer->branch === null || $lockedOffer->branch->number_code === null) {
                throw new DomainException('Cabang penawaran belum memiliki kode angka untuk nomor surat.');
            }

            $year = (int) $lockedOffer->offer_date->year;
            $sequence = (int) $lockedOffer->sequence_no;
            $suffix = $this->suffixFromNumber($lockedOffer->offer_no, $sequence);
            $expected = $this->applySuffix(
                OfferNumberService::build(
                    $sequence,
                    (int) $lockedOffer->branch->number_code,
                    $lockedOffer->offer_date,
                ),
                $sequence,
                $suffix,
            );

            if ($expected !== $lockedOffer->offer_no) {
                throw new DomainException('Nomor legacy tidak sesuai dengan sequence, cabang, dan tanggal penawaran.');
            }

            OfferNumberCounter::query()->insertOrIgnore([
                'scope_key' => $scopeKey,
                'sequence_year' => $year,
                'last_sequence' => $sequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = OfferNumberCounter::query()
                ->where('scope_key', $scopeKey)
                ->where('sequence_year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            if ($counter->last_sequence < $sequence) {
                $counter->forceFill(['last_sequence' => $sequence])->save();
            }

            $allocation = OfferNumberAllocation::query()->create([
                'offer_id' => $lockedOffer->getKey(),
                'branch_id' => $lockedOffer->branch_id,
                'scope_key' => $scopeKey,
                'sequence_year' => $year,
                'sequence_no' => $sequence,
                'number_suffix' => $suffix,
                'format_snapshot' => [
                    'schema_version' => 1,
                    'source' => 'legacy_adoption',
                    'pattern' => "{sequence}{suffix}/S.Kontrak/KJPP-HJA'R/{branch_code}/{roman_month}/{year}",
                    'branch_number_code' => (int) $lockedOffer->branch->number_code,
                    'branch_code' => $lockedOffer->branch->code,
                    'offer_date' => $lockedOffer->offer_date->format('Y-m-d'),
                ],
                'full_number' => $lockedOffer->offer_no,
                'status' => OfferNumberAllocationStatus::Allocated,
                'active_slot' => 1,
                'allocated_by' => $actor->getKey(),
                'allocated_at' => now(),
            ]);

            $lockedOffer->forceFill([
                'sequence_year' => $year,
                'number_suffix' => $suffix,
                'current_number_allocation_id' => $allocation->getKey(),
            ])->save();

            return $allocation;
        }, 5);
    }

    public function void(OfferNumberAllocation $allocation, User $actor, string $reason): OfferNumberAllocation
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Alasan void nomor penawaran wajib diisi.');
        }

        return DB::transaction(function () use ($allocation, $actor, $reason) {
            $lockedAllocation = OfferNumberAllocation::query()
                ->lockForUpdate()
                ->findOrFail($allocation->getKey());

            if ($lockedAllocation->status !== OfferNumberAllocationStatus::Allocated) {
                throw new DomainException('Hanya alokasi nomor aktif yang dapat di-void.');
            }

            $offer = Offer::query()
                ->with('engagement')
                ->lockForUpdate()
                ->findOrFail($lockedAllocation->offer_id);

            if ($offer->workOrder()->exists()) {
                throw new DomainException('Nomor penawaran yang sudah dikonversi menjadi pekerjaan tidak dapat di-void.');
            }

            if (in_array($offer->engagement?->workflow_state, [
                OfferWorkflowState::Finalized,
                OfferWorkflowState::Sent,
            ], true)) {
                throw new DomainException('Nomor dokumen final atau terkirim tidak dapat di-void.');
            }

            $lockedAllocation->forceFill([
                'status' => OfferNumberAllocationStatus::Void,
                'active_slot' => null,
                'voided_by' => $actor->getKey(),
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            if ($offer->current_number_allocation_id === $lockedAllocation->getKey()) {
                $offer->forceFill(['current_number_allocation_id' => null])->save();
            }

            return $lockedAllocation->refresh();
        }, 5);
    }

    private function normalizeScopeKey(string $scopeKey): string
    {
        $scopeKey = strtolower(trim($scopeKey));

        if ($scopeKey === '' || ! preg_match('/^[a-z0-9][a-z0-9:_-]{0,63}$/', $scopeKey)) {
            throw new DomainException('Scope nomor penawaran tidak valid.');
        }

        return $scopeKey;
    }

    private function normalizeSuffix(string $suffix): string
    {
        $suffix = strtoupper(ltrim(trim($suffix), '.'));

        if ($suffix !== '' && ! preg_match('/^[A-Z0-9-]{1,15}$/', $suffix)) {
            throw new DomainException('Suffix nomor hanya boleh berisi huruf, angka, atau tanda hubung.');
        }

        return $suffix;
    }

    private function applySuffix(string $baseNumber, int $sequence, string $suffix): string
    {
        if ($suffix === '') {
            return $baseNumber;
        }

        return preg_replace(
            '/^'.preg_quote((string) $sequence, '/').'\//',
            $sequence.'.'.$suffix.'/',
            $baseNumber,
            1,
        ) ?? $baseNumber;
    }

    private function suffixFromNumber(string $fullNumber, int $sequence): string
    {
        if (! preg_match('/^'.preg_quote((string) $sequence, '/').'(?:\.([A-Z0-9-]+))?\//i', $fullNumber, $matches)) {
            throw new DomainException('Segmen awal nomor legacy tidak sesuai dengan sequence penawaran.');
        }

        return $this->normalizeSuffix($matches[1] ?? '');
    }
}
