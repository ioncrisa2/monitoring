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
