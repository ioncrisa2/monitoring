<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Header & Branch Filter -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100">
                        Executive Dashboard Analytics & Monitoring
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Ringkasan performa produksi, SLA compliance rate, analisis bottleneck, dan fee pipeline.</p>
                </div>
                <div class="flex items-center gap-3">
                    <select wire:model.live="selectedBranchId" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-indigo-500 shadow-sm">
                        <option value="">Semua Cabang (Global)</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Overdue SLA Alert Banner (If Any) -->
            @if($overdueCount > 0)
                <div class="p-4 bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-rose-500 text-white flex items-center justify-center font-bold shrink-0 animate-bounce">🔥</div>
                        <div>
                            <div class="font-bold text-sm sm:text-base">Terdapat {{ $overdueCount }} Pekerjaan Melewati SLA (Overdue)!</div>
                            <div class="text-xs text-rose-600 dark:text-rose-400">Pekerjaan membutuhkan perhatian atau reassign PIC segera dari atasan/admin.</div>
                        </div>
                    </div>
                    <a href="{{ route('work-orders.index', ['filterOverdueOnly' => 1]) }}" wire:navigate class="self-start sm:self-auto px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow transition shrink-0">
                        Lihat Overdue &rarr;
                    </a>
                </div>
            @endif

            <!-- Top Row: KPI Metric Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <!-- Pekerjaan Aktif -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-2">
                    <div class="flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">
                        <span>Pekerjaan Aktif</span>
                        <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">📋</span>
                    </div>
                    <div class="text-3xl font-extrabold text-gray-900 dark:text-white font-mono">{{ $activeWorkOrdersCount }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Pekerjaan dalam alur produksi</div>
                </div>

                <!-- SLA Compliance Rate (%) -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-2">
                    <div class="flex items-center justify-between text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase">
                        <span>SLA Compliance Rate</span>
                        <span class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">🎯</span>
                    </div>
                    <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 font-mono">{{ $slaComplianceRate }}%</div>
                    <div class="text-xs text-emerald-600/80">Tingkat ketepatan waktu produksi</div>
                </div>

                <!-- Overdue SLA -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-2">
                    <div class="flex items-center justify-between text-xs font-semibold text-rose-500 uppercase">
                        <span>Overdue SLA</span>
                        <span class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">⏰</span>
                    </div>
                    <div class="text-3xl font-extrabold text-rose-600 dark:text-rose-400 font-mono">{{ $overdueCount }}</div>
                    <div class="text-xs text-rose-500/80">Pekerjaan terlambat dari deadline</div>
                </div>

                <!-- Selesai Bulan Ini -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-2">
                    <div class="flex items-center justify-between text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase">
                        <span>Selesai Bulan Ini</span>
                        <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">✅</span>
                    </div>
                    <div class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">{{ $completedThisMonthCount }}</div>
                    <div class="text-xs text-indigo-600/80">Laporan final terbit bulan ini</div>
                </div>
            </div>

            <!-- Financial Metrics Breakdown Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-1">
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Fee Penawaran Aktif</div>
                    <div class="text-2xl font-bold font-mono text-gray-900 dark:text-white">Rp {{ number_format($totalOfferFee, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-gray-400">Total nilai penawaran (Draft & Dikirim)</div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-1">
                    <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase">Fee Pekerjaan Berjalan (WIP)</div>
                    <div class="text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400">Rp {{ number_format($activeJobFee, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-indigo-500/80">Total nilai pekerjaan dalam alur produksi</div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-1">
                    <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase">Fee Laporan Selesai</div>
                    <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400">Rp {{ number_format($completedJobFee, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-emerald-500/80">Total nilai produksi yang sudah selesai</div>
                </div>
            </div>

            <!-- Revenue Trend Widget -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">Tren Pendapatan (Realized)</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Fee pekerjaan berstatus SELESAI, dihitung dari tanggal penyelesaian &mdash; {{ $revenueView === 'yearly' ? 'per tahun' : 'per bulan, tahun ' . now()->year }}.</p>
                    </div>
                    <div class="flex items-center p-1 rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200/80 dark:border-gray-700/60 self-start">
                        <button type="button" wire:click="$set('revenueView', 'monthly')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {{ $revenueView === 'monthly' ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                            Bulanan
                        </button>
                        <button type="button" wire:click="$set('revenueView', 'yearly')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {{ $revenueView === 'yearly' ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                            Tahunan
                        </button>
                    </div>
                </div>

                @php
                    $rvW = 760; $rvH = 220;
                    $padL = 64; $padR = 16; $padT = 16; $padB = 28;
                    $plotW = $rvW - $padL - $padR;
                    $plotH = $rvH - $padT - $padB;
                    $rvN = count($revenueValues);
                    $rvMaxRaw = count($revenueValues) ? max($revenueValues) : 0;
                    $rvMax = $rvMaxRaw > 0 ? $rvMaxRaw * 1.15 : 1;
                    $stepX = $rvN > 1 ? $plotW / ($rvN - 1) : 0;

                    $rvPoints = [];
                    foreach ($revenueValues as $i => $val) {
                        $x = $padL + ($rvN > 1 ? $i * $stepX : $plotW / 2);
                        $y = $padT + $plotH - ($val / $rvMax) * $plotH;
                        $rvPoints[] = ['x' => $x, 'y' => $y, 'val' => $val, 'label' => $revenueLabels[$i] ?? ''];
                    }

                    $linePath = '';
                    foreach ($rvPoints as $i => $p) {
                        $linePath .= ($i === 0 ? 'M' : ' L') . round($p['x'], 1) . ',' . round($p['y'], 1);
                    }
                    $areaPath = $linePath;
                    if (count($rvPoints) > 0) {
                        $baseline = $padT + $plotH;
                        $areaPath .= ' L' . round($rvPoints[count($rvPoints) - 1]['x'], 1) . ',' . $baseline;
                        $areaPath .= ' L' . round($rvPoints[0]['x'], 1) . ',' . $baseline . ' Z';
                    }

                    $compactRupiah = function ($v) {
                        if ($v >= 1_000_000_000) return 'Rp ' . rtrim(rtrim(number_format($v / 1_000_000_000, 1, ',', '.'), '0'), ',') . ' M';
                        if ($v >= 1_000_000) return 'Rp ' . rtrim(rtrim(number_format($v / 1_000_000, 1, ',', '.'), '0'), ',') . ' Jt';
                        if ($v >= 1_000) return 'Rp ' . rtrim(rtrim(number_format($v / 1_000, 1, ',', '.'), '0'), ',') . ' Rb';
                        return 'Rp ' . number_format($v, 0, ',', '.');
                    };

                    $gridLines = [];
                    for ($g = 0; $g <= 3; $g++) {
                        $gVal = $rvMax * $g / 3;
                        $gY = $padT + $plotH - ($gVal / $rvMax) * $plotH;
                        $gridLines[] = ['y' => $gY, 'label' => $compactRupiah($gVal)];
                    }
                @endphp

                @if($rvMaxRaw <= 0)
                    <div class="py-10 text-center text-sm text-gray-400 dark:text-gray-500">Belum ada pekerjaan SELESAI pada periode ini.</div>
                @else
                    <div class="relative" x-data="{ tip: null }">
                        <svg viewBox="0 0 {{ $rvW }} {{ $rvH }}" class="w-full h-auto select-none" preserveAspectRatio="xMidYMid meet">
                            <!-- Gridlines -->
                            @foreach($gridLines as $gl)
                                <line x1="{{ $padL }}" y1="{{ $gl['y'] }}" x2="{{ $rvW - $padR }}" y2="{{ $gl['y'] }}" class="stroke-gray-200 dark:stroke-gray-700" stroke-width="1" />
                                <text x="{{ $padL - 8 }}" y="{{ $gl['y'] + 3 }}" text-anchor="end" class="fill-gray-400 dark:fill-gray-500" font-size="10">{{ $gl['label'] }}</text>
                            @endforeach

                            <!-- Area fill -->
                            <path d="{{ $areaPath }}" class="fill-indigo-500" fill-opacity="0.1" stroke="none" />
                            <!-- Line -->
                            <path d="{{ $linePath }}" fill="none" class="stroke-indigo-600 dark:stroke-indigo-400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

                            <!-- X labels -->
                            @foreach($rvPoints as $i => $p)
                                @if($rvN <= 12 || $i % 2 === 0 || $i === $rvN - 1)
                                    <text x="{{ $p['x'] }}" y="{{ $rvH - 8 }}" text-anchor="middle" class="fill-gray-400 dark:fill-gray-500" font-size="10">{{ $p['label'] }}</text>
                                @endif
                            @endforeach

                            <!-- Points + hit targets -->
                            @foreach($rvPoints as $i => $p)
                                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4" class="fill-indigo-600 dark:fill-indigo-400 stroke-white dark:stroke-gray-800" stroke-width="2" />
                                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="14" fill="transparent" class="cursor-pointer"
                                        @mouseenter="tip = { x: {{ $p['x'] }}, y: {{ $p['y'] }}, label: '{{ $p['label'] }}', val: '{{ $compactRupiah($p['val']) }}' }"
                                        @mouseleave="tip = null" />
                                @if($i === $rvN - 1)
                                    <text x="{{ $p['x'] - 6 }}" y="{{ $p['y'] - 10 }}" text-anchor="end" class="fill-gray-700 dark:fill-gray-200" font-size="11" font-weight="700">{{ $compactRupiah($p['val']) }}</text>
                                @endif
                            @endforeach
                        </svg>

                        <!-- Tooltip -->
                        <div x-show="tip" x-cloak
                             :style="tip ? `left: ${(tip.x / {{ $rvW }}) * 100}%; top: ${(tip.y / {{ $rvH }}) * 100}%;` : ''"
                             class="absolute -translate-x-1/2 -translate-y-full -mt-2 px-2.5 py-1.5 bg-gray-900 dark:bg-gray-950 text-white rounded-lg shadow-lg text-xs whitespace-nowrap pointer-events-none z-10">
                            <span class="font-bold" x-text="tip ? tip.val : ''"></span>
                            <span class="text-gray-300" x-text="tip ? ' · ' + tip.label : ''"></span>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Status Funnel Lifecycle Progress Bar Widget -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">Status Funnel Pengerjaan (Active Workload)</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
                    @foreach($funnelStatuses as $statusName => $count)
                        <div class="p-3 bg-gray-50 dark:bg-gray-900/60 rounded-xl border border-gray-200 dark:border-gray-700 text-center space-y-1">
                            <div class="text-[11px] font-bold text-gray-500 dark:text-gray-400 font-mono uppercase">{{ $statusName }}</div>
                            <div class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">{{ $count }}</div>
                            <div class="text-[10px] text-gray-400">Pekerjaan</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Stage Duration & Conversion Funnel Widgets -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <!-- Rata-rata Durasi per Tahap Pipeline -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                    <div>
                        <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">Rata-rata Durasi per Tahap</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Rata-rata lama sebuah pekerjaan mengendap di tiap tahap sebelum berpindah ke tahap berikutnya.</p>
                    </div>

                    @php
                        // Emphasis pattern: the bottleneck stage carries the accent hue, the rest stay neutral context.
                        $maxStageAvg = max(1, collect($stageAverages)->max('avg'));
                        $bottleneckStage = collect($stageAverages)->sortByDesc(fn ($s) => $s['avg'])->keys()->first();
                    @endphp

                    <div class="space-y-3">
                        @foreach($stageAverages as $stage => $data)
                            @php
                                $isBottleneck = $data['avg'] > 0 && $stage === $bottleneckStage;
                                $shade = $isBottleneck ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-gray-400 dark:bg-gray-600';
                                $widthPct = $data['avg'] > 0 ? max(6, ($data['avg'] / $maxStageAvg) * 100) : 0;
                            @endphp
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-bold font-mono text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                                        {{ $stage }}
                                        @if($isBottleneck)
                                            <span class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 text-[9px] font-bold normal-case">Bottleneck</span>
                                        @endif
                                    </span>
                                    <span class="text-gray-400">{{ $data['count'] }} sampel</span>
                                </div>
                                <div class="relative h-5 bg-gray-100 dark:bg-gray-900/60 rounded-r-md overflow-hidden">
                                    @if($data['avg'] > 0)
                                        <div class="h-5 {{ $shade }} rounded-r-md flex items-center justify-end pr-2 transition-all hover:brightness-110" style="width: {{ $widthPct }}%">
                                            <span class="text-[10px] font-bold text-white whitespace-nowrap">{{ $data['avg'] }} hari</span>
                                        </div>
                                    @else
                                        <span class="absolute inset-y-0 left-2 flex items-center text-[10px] text-gray-400">Belum ada data</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Funnel Konversi Penawaran -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">Funnel Konversi Penawaran</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Win-rate dari penawaran yang sudah diputuskan (tidak termasuk yang masih Draft/Dikirim).</p>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-3xl font-extrabold font-mono {{ $conversionRate >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $conversionRate }}%</div>
                            <div class="text-[10px] text-gray-400">{{ $offerOutcomeCounts['DITERIMA'] }} dari {{ $decidedCount }} diputuskan</div>
                        </div>
                    </div>

                    @php
                        $outcomeMeta = [
                            'DRAFT' => ['label' => 'Draft', 'bar' => 'bg-gray-400 dark:bg-gray-500', 'dot' => 'bg-gray-400 dark:bg-gray-500'],
                            'DIKIRIM' => ['label' => 'Dikirim', 'bar' => 'bg-blue-500 dark:bg-blue-600', 'dot' => 'bg-blue-500 dark:bg-blue-600'],
                            'DITERIMA' => ['label' => 'Diterima', 'bar' => 'bg-emerald-500 dark:bg-emerald-600', 'dot' => 'bg-emerald-500 dark:bg-emerald-600'],
                            'TIDAK_LANJUT' => ['label' => 'Tidak Lanjut', 'bar' => 'bg-amber-500 dark:bg-amber-600', 'dot' => 'bg-amber-500 dark:bg-amber-600'],
                            'DITOLAK' => ['label' => 'Ditolak', 'bar' => 'bg-rose-500 dark:bg-rose-600', 'dot' => 'bg-rose-500 dark:bg-rose-600'],
                        ];
                        $totalOffers = max(1, array_sum($offerOutcomeCounts));
                    @endphp

                    <div class="space-y-2.5">
                        @foreach($offerOutcomeCounts as $outcome => $count)
                            @php $meta = $outcomeMeta[$outcome]; $pct = ($count / $totalOffers) * 100; @endphp
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1.5 w-28 shrink-0">
                                    <span class="w-2 h-2 rounded-full {{ $meta['dot'] }}"></span>
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $meta['label'] }}</span>
                                </div>
                                <div class="flex-1 h-5 bg-gray-100 dark:bg-gray-900/60 rounded-r-md overflow-hidden">
                                    @if($count > 0)
                                        <div class="h-5 {{ $meta['bar'] }} rounded-r-md flex items-center justify-end pr-2 transition-all hover:brightness-110" style="width: {{ max(8, $pct) }}%">
                                            <span class="text-[10px] font-bold text-white">{{ $count }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Bottleneck & Attention Table -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <div>
                        <h3 class="text-sm font-bold uppercase text-gray-700 dark:text-gray-300">Bottleneck Ranking (Pekerjaan Aging Tertinggi)</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Pekerjaan aktif yang sudah lama mengendap di status tertentu dan membutuhkan penanganan atasan/admin.</p>
                    </div>
                    <a href="{{ route('work-orders.index') }}" wire:navigate class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">Semua Pekerjaan &rarr;</a>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3">No Kontrak</th>
                                <th class="px-5 py-3">Debitur & Klien</th>
                                <th class="px-5 py-3">Aging Di Status</th>
                                <th class="px-5 py-3">Status Saat Ini</th>
                                <th class="px-5 py-3">Deadline SLA</th>
                                <th class="px-5 py-3">PIC (Surveyor / Reviewer)</th>
                                <th class="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($bottleneckJobs as $job)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-5 py-3.5 font-mono font-semibold text-gray-900 dark:text-white">
                                        {{ $job->contract_no }}
                                        <div class="text-[10px] text-gray-400 font-sans">{{ $job->offer?->branch?->code }}</div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $job->offer?->debtor?->name }}</div>
                                        <div class="text-xs text-gray-400">{{ $job->offer?->client?->name }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono font-bold text-rose-600 dark:text-rose-400">
                                        {{ $job->aging_days }} Hari
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-mono">
                                            {{ $job->current_status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-xs">
                                        <div>{{ $job->sla_date ? $job->sla_date->format('d M Y') : '-' }}</div>
                                        @if($job->is_overdue)
                                            <span class="inline-block px-2 py-0.5 mt-0.5 bg-rose-100 dark:bg-rose-900/60 text-rose-700 dark:text-rose-300 font-bold rounded text-[10px]">OVERDUE</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-xs">
                                        <div>S: {{ $job->surveyors->first()?->user?->name ?? '-' }}</div>
                                        <div>R: {{ $job->reviewers->first()?->user?->name ?? '-' }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ route('work-orders.show', $job->id) }}" wire:navigate class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-medium shadow">
                                            Detail &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-6 text-center text-gray-500">Tidak ada bottleneck pekerjaan aktif.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
