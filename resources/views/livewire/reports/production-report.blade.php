<div>
    <div class="ui-page space-y-8">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Laporan Produksi</h1>
                <p class="ui-page-description">
                    Tinjau tren pendapatan, konversi penawaran, progres pekerjaan, dan data pengiriman dalam satu laporan.
                </p>
            </div>

            <x-primary-button
                type="button"
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                wire:target="exportExcel"
                class="w-full justify-center sm:w-auto"
            >
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M10 2.75v9.5m0 0 3.25-3.25M10 12.25 6.75 9M3.5 13.75v1.5A1.75 1.75 0 0 0 5.25 17h9.5a1.75 1.75 0 0 0 1.75-1.75v-1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span wire:loading.remove wire:target="exportExcel">Ekspor Excel</span>
                <span wire:loading wire:target="exportExcel">Menyiapkan…</span>
            </x-primary-button>
        </header>

        <section class="flex flex-col gap-4 border-b border-line pb-5 sm:flex-row sm:items-end sm:justify-between" aria-labelledby="report-scope-heading">
            <div>
                <h2 id="report-scope-heading" class="ui-section-heading">Cakupan laporan</h2>
                <p class="ui-section-description">Pilihan cabang diterapkan pada seluruh analitik, tabel produksi, dan berkas ekspor.</p>
            </div>

            <div class="w-full sm:w-72">
                <x-input-label for="report-branch" value="Cabang" />
                <x-select-input id="report-branch" wire:model.live="selectedBranchId" class="mt-1">
                    <option value="">Semua cabang</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->code }})</option>
                    @endforeach
                </x-select-input>
                <p wire:loading wire:target="selectedBranchId" class="ui-help" role="status">Memperbarui laporan…</p>
            </div>
        </section>

        @php
            $rvW = 760;
            $rvH = 220;
            $padL = 64;
            $padR = 16;
            $padT = 16;
            $padB = 28;
            $plotW = $rvW - $padL - $padR;
            $plotH = $rvH - $padT - $padB;
            $rvN = count($revenueValues);
            $rvMaxRaw = count($revenueValues) ? max($revenueValues) : 0;
            $rvMax = $rvMaxRaw > 0 ? $rvMaxRaw * 1.15 : 1;
            $stepX = $rvN > 1 ? $plotW / ($rvN - 1) : 0;

            $rvPoints = [];
            foreach ($revenueValues as $i => $value) {
                $x = $padL + ($rvN > 1 ? $i * $stepX : $plotW / 2);
                $y = $padT + $plotH - ($value / $rvMax) * $plotH;
                $rvPoints[] = ['x' => $x, 'y' => $y, 'val' => $value, 'label' => $revenueLabels[$i] ?? ''];
            }

            $linePath = '';
            foreach ($rvPoints as $i => $point) {
                $linePath .= ($i === 0 ? 'M' : ' L') . round($point['x'], 1) . ',' . round($point['y'], 1);
            }

            $areaPath = $linePath;
            if (count($rvPoints) > 0) {
                $baseline = $padT + $plotH;
                $areaPath .= ' L' . round($rvPoints[count($rvPoints) - 1]['x'], 1) . ',' . $baseline;
                $areaPath .= ' L' . round($rvPoints[0]['x'], 1) . ',' . $baseline . ' Z';
            }

            $compactRupiah = function ($value) {
                if ($value >= 1_000_000_000) {
                    return 'Rp ' . rtrim(rtrim(number_format($value / 1_000_000_000, 1, ',', '.'), '0'), ',') . ' M';
                }

                if ($value >= 1_000_000) {
                    return 'Rp ' . rtrim(rtrim(number_format($value / 1_000_000, 1, ',', '.'), '0'), ',') . ' Jt';
                }

                if ($value >= 1_000) {
                    return 'Rp ' . rtrim(rtrim(number_format($value / 1_000, 1, ',', '.'), '0'), ',') . ' Rb';
                }

                return 'Rp ' . number_format($value, 0, ',', '.');
            };

            $gridLines = [];
            for ($g = 0; $g <= 3; $g++) {
                $gridValue = $rvMax * $g / 3;
                $gridY = $padT + $plotH - ($gridValue / $rvMax) * $plotH;
                $gridLines[] = ['y' => $gridY, 'label' => $compactRupiah($gridValue)];
            }

            $outcomeMeta = [
                'DRAFT' => ['label' => 'Draft', 'bar' => 'bg-gray-400 dark:bg-gray-500', 'dot' => 'bg-gray-400 dark:bg-gray-500'],
                'DIKIRIM' => ['label' => 'Dikirim', 'bar' => 'bg-blue-500 dark:bg-blue-600', 'dot' => 'bg-blue-500 dark:bg-blue-600'],
                'DITERIMA' => ['label' => 'Diterima', 'bar' => 'bg-emerald-500 dark:bg-emerald-600', 'dot' => 'bg-emerald-500 dark:bg-emerald-600'],
                'TIDAK_LANJUT' => ['label' => 'Tidak lanjut', 'bar' => 'bg-amber-500 dark:bg-amber-600', 'dot' => 'bg-amber-500 dark:bg-amber-600'],
                'DITOLAK' => ['label' => 'Ditolak', 'bar' => 'bg-rose-500 dark:bg-rose-600', 'dot' => 'bg-rose-500 dark:bg-rose-600'],
            ];
            $totalOffers = max(1, array_sum($offerOutcomeCounts));
        @endphp

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
            <section class="ui-surface p-5 sm:p-6" aria-labelledby="revenue-heading">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 id="revenue-heading" class="ui-section-heading">Tren pendapatan terealisasi</h2>
                        <p id="revenue-description" class="ui-section-description">
                            Fee pekerjaan selesai berdasarkan tanggal penyelesaian—{{ $revenueView === 'yearly' ? 'diringkas per tahun' : 'diringkas per bulan pada ' . now()->year }}.
                        </p>
                    </div>

                    <div class="inline-flex self-start rounded-ui border border-line bg-surface-subtle p-1" role="group" aria-label="Rentang tren pendapatan">
                        <button
                            type="button"
                            wire:click="$set('revenueView', 'monthly')"
                            aria-pressed="{{ $revenueView === 'monthly' ? 'true' : 'false' }}"
                            class="min-h-9 rounded-ui-sm px-3 text-xs font-semibold transition {{ $revenueView === 'monthly' ? 'bg-surface text-brand' : 'text-ink-muted hover:text-ink' }}"
                        >
                            Bulanan
                        </button>
                        <button
                            type="button"
                            wire:click="$set('revenueView', 'yearly')"
                            aria-pressed="{{ $revenueView === 'yearly' ? 'true' : 'false' }}"
                            class="min-h-9 rounded-ui-sm px-3 text-xs font-semibold transition {{ $revenueView === 'yearly' ? 'bg-surface text-brand' : 'text-ink-muted hover:text-ink' }}"
                        >
                            Tahunan
                        </button>
                    </div>
                </div>

                @if($rvMaxRaw <= 0)
                    <div class="ui-empty-state">Belum ada pekerjaan selesai pada periode analitik ini.</div>
                @else
                    <div class="relative mt-5" x-data="{ tip: null }">
                        <svg
                            viewBox="0 0 {{ $rvW }} {{ $rvH }}"
                            class="h-auto w-full select-none"
                            preserveAspectRatio="xMidYMid meet"
                            role="img"
                            aria-labelledby="revenue-heading revenue-description"
                        >
                            @foreach($gridLines as $gridLine)
                                <line x1="{{ $padL }}" y1="{{ $gridLine['y'] }}" x2="{{ $rvW - $padR }}" y2="{{ $gridLine['y'] }}" class="stroke-line" stroke-width="1" />
                                <text x="{{ $padL - 8 }}" y="{{ $gridLine['y'] + 4 }}" text-anchor="end" class="fill-ink-muted" font-size="11">{{ $gridLine['label'] }}</text>
                            @endforeach

                            <path d="{{ $areaPath }}" class="fill-brand" fill-opacity="0.1" stroke="none" />
                            <path d="{{ $linePath }}" fill="none" class="stroke-brand" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

                            @foreach($rvPoints as $i => $point)
                                @if($rvN <= 12 || $i % 2 === 0 || $i === $rvN - 1)
                                    <text x="{{ $point['x'] }}" y="{{ $rvH - 8 }}" text-anchor="middle" class="fill-ink-muted" font-size="11">{{ $point['label'] }}</text>
                                @endif
                            @endforeach

                            @foreach($rvPoints as $i => $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" class="fill-brand stroke-surface" stroke-width="2" />
                                <circle
                                    cx="{{ $point['x'] }}"
                                    cy="{{ $point['y'] }}"
                                    r="14"
                                    fill="transparent"
                                    class="cursor-pointer"
                                    tabindex="0"
                                    role="button"
                                    aria-label="{{ $point['label'] }}: {{ $compactRupiah($point['val']) }}"
                                    @mouseenter="tip = { x: {{ $point['x'] }}, y: {{ $point['y'] }}, label: '{{ $point['label'] }}', val: '{{ $compactRupiah($point['val']) }}' }"
                                    @mouseleave="tip = null"
                                    @focus="tip = { x: {{ $point['x'] }}, y: {{ $point['y'] }}, label: '{{ $point['label'] }}', val: '{{ $compactRupiah($point['val']) }}' }"
                                    @blur="tip = null"
                                />
                                @if($i === $rvN - 1)
                                    <text x="{{ $point['x'] - 6 }}" y="{{ $point['y'] - 10 }}" text-anchor="end" class="fill-ink" font-size="11" font-weight="700">{{ $compactRupiah($point['val']) }}</text>
                                @endif
                            @endforeach
                        </svg>

                        <div
                            x-show="tip"
                            x-cloak
                            :style="tip ? `left: ${(tip.x / {{ $rvW }}) * 100}%; top: ${(tip.y / {{ $rvH }}) * 100}%;` : ''"
                            class="ui-surface-raised pointer-events-none absolute z-10 -mt-2 -translate-x-1/2 -translate-y-full whitespace-nowrap bg-gray-950 px-2.5 py-1.5 text-xs text-white"
                        >
                            <span class="font-semibold" x-text="tip ? tip.val : ''"></span>
                            <span class="text-gray-300" x-text="tip ? ' · ' + tip.label : ''"></span>
                        </div>
                    </div>
                @endif
            </section>

            <section class="ui-surface p-5 sm:p-6" aria-labelledby="conversion-heading">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="conversion-heading" class="ui-section-heading">Konversi penawaran</h2>
                        <p class="ui-section-description">Rasio penawaran diterima dari seluruh penawaran yang telah diputuskan.</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ $conversionRate }}%</div>
                        <div class="mt-1 text-xs text-ink-muted tabular-nums">{{ $offerOutcomeCounts['DITERIMA'] }} dari {{ $decidedCount }}</div>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @foreach($offerOutcomeCounts as $outcome => $count)
                        @php
                            $meta = $outcomeMeta[$outcome];
                            $percentage = ($count / $totalOffers) * 100;
                        @endphp
                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                <span class="flex items-center gap-2 font-medium text-ink-secondary">
                                    <span class="size-2 rounded-full {{ $meta['dot'] }}" aria-hidden="true"></span>
                                    {{ $meta['label'] }}
                                </span>
                                <span class="font-semibold text-ink tabular-nums">{{ $count }}</span>
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-surface-muted"
                                role="progressbar"
                                aria-label="{{ $meta['label'] }}"
                                aria-valuenow="{{ $count }}"
                                aria-valuemin="0"
                                aria-valuemax="{{ array_sum($offerOutcomeCounts) }}"
                            >
                                @if($count > 0)
                                    <div class="h-full rounded-full {{ $meta['bar'] }}" style="width: {{ max(3, $percentage) }}%"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section aria-labelledby="production-table-heading">
            <div class="ui-toolbar mb-4">
                <div>
                    <h2 id="production-table-heading" class="ui-section-heading">Data produksi</h2>
                    <p class="ui-section-description">
                        {{ $workOrders->total() }} pekerjaan sesuai filter. Status dan tanggal hanya memengaruhi tabel ini serta berkas ekspor.
                    </p>
                </div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-4 border-b border-line pb-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="production-status" value="Status pekerjaan" />
                    <x-select-input id="production-status" wire:model.live="selectedStatus" class="mt-1">
                        <option value="">Semua status</option>
                        <option value="PERSIAPAN">Persiapan</option>
                        <option value="SURVEY">Survey</option>
                        <option value="PENGERJAAN">Pengerjaan</option>
                        <option value="REVIEW">Review</option>
                        <option value="CETAK">Cetak</option>
                        <option value="SELESAI">Selesai</option>
                        <option value="BATAL">Batal</option>
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="production-from-date" value="Dari tanggal" />
                    <x-text-input id="production-from-date" wire:model.live="fromDate" type="date" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="production-to-date" value="Sampai tanggal" />
                    <x-text-input id="production-to-date" wire:model.live="toDate" type="date" class="mt-1" />
                </div>

                <p wire:loading wire:target="selectedStatus,fromDate,toDate" class="ui-help sm:col-span-3" role="status">Memperbarui tabel produksi…</p>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <caption class="sr-only">Ringkasan pekerjaan produksi sesuai filter</caption>
                    <thead>
                        <tr>
                            <th scope="col">Kontrak</th>
                            <th scope="col">Debitur dan klien</th>
                            <th scope="col" class="text-right">Fee</th>
                            <th scope="col">Operasional dan SLA</th>
                            <th scope="col">Laporan resmi</th>
                            <th scope="col">Pengiriman</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($workOrders as $workOrder)
                            <tr wire:key="production-work-order-{{ $workOrder->id }}">
                                <td>
                                    <div class="font-mono text-sm font-semibold text-ink">{{ $workOrder->contract_no }}</div>
                                    <div class="mt-1 text-xs text-ink-muted">
                                        {{ $workOrder->offer?->branch?->code ?? '-' }} · {{ $workOrder->contract_date ? $workOrder->contract_date->format('d M Y') : '-' }}
                                    </div>
                                </td>

                                <td>
                                    <div class="font-medium text-ink">{{ $workOrder->offer?->debtor?->name ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-ink-muted">{{ $workOrder->offer?->client?->name ?? '-' }}</div>
                                </td>

                                <td class="text-right font-semibold text-ink tabular-nums">
                                    Rp {{ number_format($workOrder->offer?->fee ?? 0, 0, ',', '.') }}
                                </td>

                                <td>
                                    <x-status-badge :status="$workOrder->current_status" />
                                    <div class="mt-1.5 text-xs text-ink-muted tabular-nums">SLA: {{ $workOrder->sla_date ? $workOrder->sla_date->format('d M Y') : '-' }}</div>
                                    <x-sla-badge :overdue="$workOrder->is_overdue" overdue-label="Terlambat" class="mt-1" />
                                </td>

                                <td class="text-xs leading-5">
                                    @forelse($workOrder->reports as $report)
                                        <div class="font-mono font-semibold text-ink">{{ $report->report_no }}</div>
                                        <div class="text-emerald-700 tabular-nums dark:text-emerald-400">Nilai Rp {{ number_format($report->report_value ?? 0, 0, ',', '.') }}</div>
                                    @empty
                                        <span class="text-ink-muted">Belum terbit</span>
                                    @endforelse
                                </td>

                                <td class="text-xs leading-5">
                                    @php($delivery = $workOrder->reports->first()?->delivery)
                                    @if($delivery)
                                        <div class="font-medium text-ink">{{ $delivery->courier }}</div>
                                        <div class="font-mono text-ink-secondary">Resi {{ $delivery->tracking_no ?? '-' }}</div>
                                        <div class="text-ink-muted tabular-nums">Dikirim {{ $delivery->sent_date ? $delivery->sent_date->format('d M Y') : '-' }}</div>
                                    @else
                                        <span class="text-ink-muted">Belum dikirim</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="ui-empty-state">Tidak ada data produksi yang sesuai dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($workOrders->hasPages())
                <div class="mt-4">{{ $workOrders->links() }}</div>
            @endif
        </section>
    </div>
</div>
