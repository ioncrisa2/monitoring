<div>
    <div class="ui-page space-y-6">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Penawaran</h1>
                <p class="ui-page-description">Kelola penawaran jasa penilaian, nilai fee, keputusan klien, dan konversi menjadi pekerjaan.</p>
            </div>

            <a href="{{ route('offers.create') }}" wire:navigate class="ui-btn ui-btn-primary w-full sm:w-auto">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5" />
                </svg>
                Buat penawaran
            </a>
        </header>

        @if(session()->has('message'))
            <x-flash-message>{{ session('message') }}</x-flash-message>
        @endif

        <section aria-labelledby="offer-list-heading">
            <div class="ui-toolbar mb-4">
                <div>
                    <h2 id="offer-list-heading" class="ui-section-heading">Daftar penawaran</h2>
                    <p class="ui-section-description">{{ $offers->total() }} penawaran ditemukan.</p>
                </div>

                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-3 md:w-auto">
                    <div class="relative sm:min-w-72">
                        <x-input-label for="offer-search" value="Cari penawaran" class="sr-only" />
                        <x-text-input
                            id="offer-search"
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="Cari nomor, debitur, atau klien"
                            class="pl-10"
                        />
                        <svg class="pointer-events-none absolute left-3 top-3 size-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                        </svg>
                    </div>

                    <div>
                        <x-input-label for="offer-outcome-filter" value="Filter status" class="sr-only" />
                        <x-select-input id="offer-outcome-filter" wire:model.live="filterOutcome" aria-label="Filter status penawaran">
                            <option value="">Semua status</option>
                            <option value="DRAFT">Draft</option>
                            <option value="DIKIRIM">Dikirim</option>
                            <option value="DITERIMA">Diterima</option>
                            <option value="TIDAK_LANJUT">Tidak lanjut</option>
                            <option value="DITOLAK">Ditolak</option>
                        </x-select-input>
                    </div>

                    <div>
                        <x-input-label for="offer-branch-filter" value="Filter cabang" class="sr-only" />
                        <x-select-input id="offer-branch-filter" wire:model.live="filterBranchId" aria-label="Filter cabang penawaran">
                            <option value="">Semua cabang</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <p wire:loading wire:target="search,filterOutcome,filterBranchId" class="ui-help sm:col-span-3" role="status">Memperbarui daftar…</p>
                </div>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <caption class="sr-only">Daftar penawaran jasa penilaian</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nomor dan tanggal</th>
                            <th scope="col">Debitur dan klien</th>
                            <th scope="col">Cabang</th>
                            <th scope="col">Fee dan DPP</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($offers as $offer)
                            <tr wire:key="offer-row-{{ $offer->id }}">
                                <td>
                                    <div class="font-mono text-sm font-semibold text-ink">{{ $offer->offer_no }}</div>
                                    <div class="mt-1 text-xs text-ink-muted tabular-nums">{{ $offer->offer_date->format('d M Y') }}</div>
                                </td>
                                <td>
                                    <div class="font-medium text-ink">{{ $offer->debtor?->name ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-ink-muted">Klien: {{ $offer->client?->name ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="font-mono text-xs font-semibold text-ink-secondary">{{ $offer->branch?->code ?? '-' }}</span>
                                </td>
                                <td class="tabular-nums">
                                    <div class="font-semibold text-ink">Rp {{ number_format($offer->fee, 0, ',', '.') }}</div>
                                    <div class="mt-1 text-xs text-ink-muted">DPP Rp {{ number_format($offer->dpp, 0, ',', '.') }}</div>
                                </td>
                                <td><x-offer-outcome-badge :outcome="$offer->outcome" /></td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-1">
                                        @if($offer->outcome === 'DITERIMA' && $offer->workOrder)
                                            <a
                                                href="{{ route('work-orders.show', $offer->workOrder->id) }}"
                                                wire:navigate
                                                class="ui-text-action"
                                                aria-label="Buka pekerjaan dari penawaran {{ $offer->offer_no }}"
                                            >
                                                Lihat pekerjaan
                                            </a>
                                        @elseif($offer->outcome !== 'TIDAK_LANJUT' && $offer->outcome !== 'DITOLAK')
                                            <button
                                                wire:click="prepareConvert({{ $offer->id }})"
                                                type="button"
                                                class="ui-text-action"
                                                aria-label="Jadikan penawaran {{ $offer->offer_no }} sebagai pekerjaan"
                                            >
                                                Jadikan pekerjaan
                                            </button>
                                        @endif

                                        <button
                                            wire:click="edit({{ $offer->id }})"
                                            type="button"
                                            class="ui-text-action"
                                            aria-label="Edit penawaran {{ $offer->offer_no }}"
                                        >
                                            Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="ui-empty-state">
                                    {{ $search || $filterOutcome || $filterBranchId ? 'Tidak ada penawaran yang cocok dengan pencarian atau filter.' : 'Belum ada penawaran. Buat penawaran pertama untuk memulai.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($offers->hasPages())
                <div class="mt-4">{{ $offers->links() }}</div>
            @endif
        </section>
    </div>

    @if($showModal)
        <x-modal name="offer-editor" :show="$showModal" close-property="showModal" maxWidth="xl" labelledby="offer-editor-title" focusable>
            <div class="ui-modal-header">
                <div>
                    <h2 id="offer-editor-title" class="ui-modal-title">Edit penawaran</h2>
                    <p class="mt-1 text-sm text-ink-muted">Perbarui identitas, nilai, atau status penawaran.</p>
                </div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form edit penawaran">&times;</button>
            </div>

            <form wire:submit="save">
                <div class="ui-modal-body">
                    @include('livewire.offers.partials.form-fields', ['formId' => 'edit-offer'])
                </div>
                <div class="ui-modal-footer">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')" class="w-full sm:w-auto">Batal</x-secondary-button>
                    <x-primary-button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="w-full sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="save">Simpan perubahan</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif

    @if($showConvertModal && $convertingOffer)
        <x-modal name="offer-conversion" :show="$showConvertModal" close-property="showConvertModal" maxWidth="sm" labelledby="offer-conversion-title" focusable>
            <div class="ui-modal-header">
                <div>
                    <h2 id="offer-conversion-title" class="ui-modal-title">Jadikan pekerjaan</h2>
                    <p class="mt-1 text-sm text-ink-muted">Penawaran akan diterima dan pekerjaan aktif baru akan dibuat.</p>
                </div>
                <button x-on:click="$dispatch('close')" type="button" class="ui-icon-btn -my-1 h-9 w-9" aria-label="Tutup form konversi">&times;</button>
            </div>

            <form wire:submit="convertToJob">
                <div class="ui-modal-body space-y-5">
                    <dl class="ui-surface-subtle divide-y divide-line px-4 text-sm">
                        <div class="flex justify-between gap-4 py-3">
                            <dt class="text-ink-muted">Nomor penawaran</dt>
                            <dd class="break-all text-right font-mono font-semibold text-ink">{{ $convertingOffer->offer_no }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-3">
                            <dt class="text-ink-muted">Debitur</dt>
                            <dd class="text-right font-medium text-ink">{{ $convertingOffer->debtor?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-3">
                            <dt class="text-ink-muted">Klien</dt>
                            <dd class="text-right font-medium text-ink">{{ $convertingOffer->client?->name ?? '-' }}</dd>
                        </div>
                    </dl>

                    <div>
                        <x-input-label for="conversion-sla-date" value="Tenggat SLA" />
                        <x-text-input
                            id="conversion-sla-date"
                            wire:model="sla_date"
                            type="date"
                            class="mt-1"
                            aria-describedby="conversion-sla-date-error"
                            aria-invalid="{{ $errors->has('sla_date') ? 'true' : 'false' }}"
                        />
                        <x-input-error id="conversion-sla-date-error" :messages="$errors->get('sla_date')" />
                    </div>

                    <label for="conversion-survey-required" class="flex min-h-10 items-start gap-3 text-sm text-ink-secondary">
                        <input
                            id="conversion-survey-required"
                            wire:model="survey_required"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-line-strong text-brand focus:ring-brand"
                        >
                        <span>
                            <span class="block font-medium text-ink">Membutuhkan survei lapangan</span>
                            <span class="mt-0.5 block text-xs leading-5 text-ink-muted">Tahap survei akan dimasukkan ke alur pekerjaan.</span>
                        </span>
                    </label>
                </div>

                <div class="ui-modal-footer">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')" class="w-full sm:w-auto">Batal</x-secondary-button>
                    <x-primary-button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="convertToJob"
                        class="w-full sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="convertToJob">Buat pekerjaan</span>
                        <span wire:loading wire:target="convertToJob">Membuat…</span>
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
