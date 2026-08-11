@php($formId = $formId ?? 'offer')

<div class="space-y-8">
    <section aria-labelledby="{{ $formId }}-identity-heading">
        <div class="mb-4">
            <h2 id="{{ $formId }}-identity-heading" class="ui-section-heading">Identitas penawaran</h2>
            <p class="ui-section-description">Tentukan nomor urut, tanggal, dan cabang penerbit penawaran.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <x-input-label for="{{ $formId }}-sequence-no" value="Nomor urut" />
                <x-text-input
                    id="{{ $formId }}-sequence-no"
                    wire:model.live="sequence_no"
                    type="number"
                    min="1"
                    class="mt-1"
                    aria-describedby="{{ $formId }}-sequence-help {{ $formId }}-sequence-error"
                    aria-invalid="{{ $errors->has('sequence_no') ? 'true' : 'false' }}"
                />
                <p id="{{ $formId }}-sequence-help" class="ui-help">
                    Nomor terakhir tahun {{ \Carbon\Carbon::parse($offer_date ?: now())->year }}: {{ $this->lastSequenceForYear() }}
                </p>
                <x-input-error id="{{ $formId }}-sequence-error" :messages="$errors->get('sequence_no')" />
            </div>

            <div>
                <x-input-label for="{{ $formId }}-offer-date" value="Tanggal penawaran" />
                <x-text-input
                    id="{{ $formId }}-offer-date"
                    wire:model.live="offer_date"
                    type="date"
                    class="mt-1"
                    aria-describedby="{{ $formId }}-offer-date-error"
                    aria-invalid="{{ $errors->has('offer_date') ? 'true' : 'false' }}"
                />
                <x-input-error id="{{ $formId }}-offer-date-error" :messages="$errors->get('offer_date')" />
            </div>

            <div>
                <x-input-label for="{{ $formId }}-branch" value="Cabang" />
                <x-select-input
                    id="{{ $formId }}-branch"
                    wire:model.live="branch_id"
                    class="mt-1"
                    aria-describedby="{{ $formId }}-branch-error"
                    aria-invalid="{{ $errors->has('branch_id') ? 'true' : 'false' }}"
                >
                    <option value="">Pilih cabang</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error id="{{ $formId }}-branch-error" :messages="$errors->get('branch_id')" />
            </div>
        </div>

        <div class="ui-surface-subtle mt-4 px-4 py-3" role="status" aria-live="polite">
            <div class="text-xs font-medium text-ink-muted">Nomor penawaran otomatis</div>
            <output class="mt-1 block break-all font-mono text-sm font-semibold text-ink">
                {{ $offer_no ?: 'Pilih cabang dan isi nomor urut untuk melihat pratinjau' }}
            </output>
        </div>
    </section>

    <section class="border-t border-line pt-6" aria-labelledby="{{ $formId }}-parties-heading">
        <div class="mb-4">
            <h2 id="{{ $formId }}-parties-heading" class="ui-section-heading">Pihak terkait</h2>
            <p class="ui-section-description">Pilih objek penilaian, pemberi tugas, dan pengguna laporan.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <x-input-label for="{{ $formId }}-debtor" value="Debitur (objek)" />
                <x-select-input
                    id="{{ $formId }}-debtor"
                    wire:model="debtor_id"
                    class="mt-1"
                    aria-describedby="{{ $formId }}-debtor-error"
                    aria-invalid="{{ $errors->has('debtor_id') ? 'true' : 'false' }}"
                >
                    <option value="">Pilih debitur</option>
                    @foreach($debtors as $debtor)
                        <option value="{{ $debtor->id }}">{{ $debtor->name }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error id="{{ $formId }}-debtor-error" :messages="$errors->get('debtor_id')" />
            </div>

            <div>
                <x-input-label for="{{ $formId }}-client" value="Pemberi tugas (klien)" />
                <x-select-input
                    id="{{ $formId }}-client"
                    wire:model="client_id"
                    class="mt-1"
                    aria-describedby="{{ $formId }}-client-error"
                    aria-invalid="{{ $errors->has('client_id') ? 'true' : 'false' }}"
                >
                    <option value="">Pilih klien</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error id="{{ $formId }}-client-error" :messages="$errors->get('client_id')" />
            </div>

            <div>
                <x-input-label for="{{ $formId }}-report-user" value="Pengguna laporan (opsional)" />
                <x-select-input
                    id="{{ $formId }}-report-user"
                    wire:model="report_user_id"
                    class="mt-1"
                    aria-describedby="{{ $formId }}-report-user-help {{ $formId }}-report-user-error"
                    aria-invalid="{{ $errors->has('report_user_id') ? 'true' : 'false' }}"
                >
                    <option value="">Sama dengan klien</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </x-select-input>
                <p id="{{ $formId }}-report-user-help" class="ui-help">Kosongkan jika pengguna laporan sama dengan pemberi tugas.</p>
                <x-input-error id="{{ $formId }}-report-user-error" :messages="$errors->get('report_user_id')" />
            </div>
        </div>
    </section>

    <section class="border-t border-line pt-6" aria-labelledby="{{ $formId }}-financial-heading">
        <div class="mb-4">
            <h2 id="{{ $formId }}-financial-heading" class="ui-section-heading">Keuangan dan pajak</h2>
            <p class="ui-section-description">Masukkan nilai yang dapat diedit; DPP dan pajak dihitung otomatis.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <x-input-label for="{{ $formId }}-fee" value="Fee penawaran" />
                <x-text-input
                    id="{{ $formId }}-fee"
                    wire:model.live="fee"
                    type="number"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    class="mt-1"
                    aria-describedby="{{ $formId }}-fee-error"
                    aria-invalid="{{ $errors->has('fee') ? 'true' : 'false' }}"
                />
                <x-input-error id="{{ $formId }}-fee-error" :messages="$errors->get('fee')" />
            </div>

            <div>
                <x-input-label for="{{ $formId }}-ta" value="TA operasional" />
                <x-text-input
                    id="{{ $formId }}-ta"
                    wire:model.live="ta"
                    type="number"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    class="mt-1"
                    aria-describedby="{{ $formId }}-ta-error"
                    aria-invalid="{{ $errors->has('ta') ? 'true' : 'false' }}"
                />
                <x-input-error id="{{ $formId }}-ta-error" :messages="$errors->get('ta')" />
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-1 border-y border-line sm:grid-cols-3 sm:divide-x sm:divide-line" aria-label="Hasil kalkulasi pajak">
            <div class="py-4 sm:pr-5">
                <dt class="text-sm text-ink-secondary">DPP</dt>
                <dd class="mt-1 text-lg font-semibold text-ink tabular-nums">Rp {{ number_format($dpp, 0, ',', '.') }}</dd>
            </div>
            <div class="border-t border-line py-4 sm:border-t-0 sm:px-5">
                <dt class="text-sm text-ink-secondary">PPN (11%)</dt>
                <dd class="mt-1 text-lg font-semibold text-ink tabular-nums">Rp {{ number_format($ppn, 0, ',', '.') }}</dd>
            </div>
            <div class="border-t border-line py-4 sm:border-t-0 sm:pl-5">
                <dt class="text-sm text-ink-secondary">PPh (2%)</dt>
                <dd class="mt-1 text-lg font-semibold text-ink tabular-nums">Rp {{ number_format($pph, 0, ',', '.') }}</dd>
            </div>
        </dl>
    </section>

    <section class="border-t border-line pt-6" aria-labelledby="{{ $formId }}-status-heading">
        <div class="mb-4">
            <h2 id="{{ $formId }}-status-heading" class="ui-section-heading">Status dan catatan</h2>
            <p class="ui-section-description">Catat posisi penawaran saat ini dan informasi tambahan untuk tim.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <div>
                <x-input-label for="{{ $formId }}-outcome" value="Status penawaran" />
                <x-select-input
                    id="{{ $formId }}-outcome"
                    wire:model="outcome"
                    class="mt-1"
                    aria-describedby="{{ $formId }}-outcome-error"
                    aria-invalid="{{ $errors->has('outcome') ? 'true' : 'false' }}"
                >
                    <option value="DRAFT">Draft awal</option>
                    <option value="DIKIRIM">Dikirim ke klien</option>
                    <option value="DITERIMA">Disetujui klien</option>
                    <option value="TIDAK_LANJUT">Tidak dilanjutkan</option>
                    <option value="DITOLAK">Ditolak atau dibatalkan</option>
                </x-select-input>
                <x-input-error id="{{ $formId }}-outcome-error" :messages="$errors->get('outcome')" />
            </div>

            <div>
                <x-input-label for="{{ $formId }}-note" value="Catatan" />
                <x-textarea-input
                    id="{{ $formId }}-note"
                    wire:model="note"
                    rows="3"
                    class="mt-1"
                    placeholder="Tambahkan catatan bila diperlukan"
                    aria-describedby="{{ $formId }}-note-error"
                    aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}"
                ></x-textarea-input>
                <x-input-error id="{{ $formId }}-note-error" :messages="$errors->get('note')" />
            </div>
        </div>
    </section>
</div>
