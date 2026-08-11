<div>
    <div class="ui-page space-y-8">
        <header class="ui-page-header">
            <div>
                <h1 class="ui-page-title">Dashboard Operasional</h1>
                <p class="ui-page-description">
                    Pantau pekerjaan aktif, kepatuhan SLA, hambatan produksi, dan nilai fee dalam satu ringkasan.
                </p>
            </div>

            <div class="w-full sm:w-auto">
                <x-input-label for="dashboard-branch" value="Cabang" class="sr-only" />
                <x-select-input
                    id="dashboard-branch"
                    wire:model.live="selectedBranchId"
                    class="w-full sm:min-w-64"
                    aria-label="Filter dashboard berdasarkan cabang"
                >
                    <option value="">Semua cabang</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->code }})</option>
                    @endforeach
                </x-select-input>
                <p wire:loading wire:target="selectedBranchId" class="ui-help" role="status">
                    Memperbarui ringkasan…
                </p>
            </div>
        </header>

        @if($overdueCount > 0)
            <div class="flex flex-col gap-3 rounded-ui border border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-900/70 dark:bg-rose-950/40 sm:flex-row sm:items-center sm:justify-between" role="alert">
                <p class="text-sm leading-6 text-rose-800 dark:text-rose-200">
                    <span class="font-semibold tabular-nums">{{ $overdueCount }} pekerjaan</span> melewati SLA dan membutuhkan perhatian segera.
                </p>
                <a
                    href="{{ route('work-orders.index', ['filterOverdueOnly' => 1]) }}"
                    wire:navigate
                    class="inline-flex min-h-10 shrink-0 items-center text-sm font-semibold text-rose-700 hover:text-rose-900 dark:text-rose-300 dark:hover:text-rose-100"
                >
                    Lihat pekerjaan terlambat
                    <span aria-hidden="true" class="ml-1">→</span>
                </a>
            </div>
        @endif

        <section aria-labelledby="dashboard-kpi-heading">
            <h2 id="dashboard-kpi-heading" class="sr-only">Indikator utama operasional</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="ui-surface p-5">
                    <dt class="text-sm font-medium text-ink-secondary">Pekerjaan aktif</dt>
                    <dd class="mt-3 text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ $activeWorkOrdersCount }}</dd>
                    <p class="mt-2 text-xs leading-5 text-ink-muted">Masih berada dalam alur produksi</p>
                </div>

                <div class="ui-surface p-5">
                    <dt class="text-sm font-medium text-ink-secondary">Kepatuhan SLA</dt>
                    <dd class="mt-3 text-3xl font-semibold tracking-tight text-emerald-700 tabular-nums dark:text-emerald-400">{{ $slaComplianceRate }}%</dd>
                    <p class="mt-2 text-xs leading-5 text-ink-muted">Pekerjaan selesai tepat waktu</p>
                </div>

                <div class="ui-surface p-5">
                    <dt class="text-sm font-medium text-ink-secondary">Melewati SLA</dt>
                    <dd class="mt-3 text-3xl font-semibold tracking-tight text-rose-700 tabular-nums dark:text-rose-400">{{ $overdueCount }}</dd>
                    <p class="mt-2 text-xs leading-5 text-ink-muted">Sudah melampaui tenggat produksi</p>
                </div>

                <div class="ui-surface p-5">
                    <dt class="text-sm font-medium text-ink-secondary">Selesai bulan ini</dt>
                    <dd class="mt-3 text-3xl font-semibold tracking-tight text-brand tabular-nums">{{ $completedThisMonthCount }}</dd>
                    <p class="mt-2 text-xs leading-5 text-ink-muted">Laporan final yang telah terbit</p>
                </div>
            </dl>
        </section>

        <section aria-labelledby="financial-summary-heading">
            <div class="mb-4">
                <h2 id="financial-summary-heading" class="ui-section-heading">Ringkasan nilai pekerjaan</h2>
                <p class="ui-section-description">Perbandingan nilai penawaran, pekerjaan berjalan, dan pekerjaan yang telah selesai.</p>
            </div>

            <dl class="grid grid-cols-1 border-y border-line sm:grid-cols-3 sm:divide-x sm:divide-line">
                <div class="py-4 sm:pr-6">
                    <dt class="text-sm text-ink-secondary">Penawaran aktif</dt>
                    <dd class="mt-1 text-xl font-semibold text-ink tabular-nums">Rp {{ number_format($totalOfferFee, 0, ',', '.') }}</dd>
                </div>
                <div class="border-t border-line py-4 sm:border-t-0 sm:px-6">
                    <dt class="text-sm text-ink-secondary">Pekerjaan berjalan</dt>
                    <dd class="mt-1 text-xl font-semibold text-ink tabular-nums">Rp {{ number_format($activeJobFee, 0, ',', '.') }}</dd>
                </div>
                <div class="border-t border-line py-4 sm:border-t-0 sm:pl-6">
                    <dt class="text-sm text-ink-secondary">Terealisasi</dt>
                    <dd class="mt-1 text-xl font-semibold text-ink tabular-nums">Rp {{ number_format($completedJobFee, 0, ',', '.') }}</dd>
                </div>
            </dl>
        </section>

        @php
            $stagesWithData = collect($stageAverages)->filter(fn ($stage) => $stage['count'] > 0);
            $bottleneckStage = $stagesWithData->isNotEmpty() ? $stagesWithData->sortByDesc('avg')->keys()->first() : null;
            $avgStageDuration = $stagesWithData->isNotEmpty() ? $stagesWithData->avg('avg') : null;
        @endphp

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2" aria-label="Pipeline dan kesehatan SLA">
            <div class="ui-surface p-5 sm:p-6">
                <h2 class="ui-section-heading">Pipeline pekerjaan</h2>
                <p class="ui-section-description">Sebaran pekerjaan aktif pada setiap tahap produksi.</p>

                <dl class="mt-5 divide-y divide-line">
                    @foreach($funnelStatuses as $statusName => $count)
                        @if($statusName !== 'SELESAI')
                            <div class="flex min-h-10 items-center justify-between gap-4 py-2">
                                <dt class="text-sm text-ink-secondary">{{ ucfirst(strtolower($statusName)) }}</dt>
                                <dd class="text-sm font-semibold text-ink tabular-nums">{{ $count }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>

            <div class="ui-surface p-5 sm:p-6">
                <h2 class="ui-section-heading">Kesehatan SLA</h2>
                <p class="ui-section-description">Tahap yang paling lama tertahan dan rata-rata durasi proses.</p>

                <dl class="mt-5 divide-y divide-line">
                    <div class="flex min-h-10 items-center justify-between gap-4 py-2">
                        <dt class="text-sm text-ink-secondary">Pekerjaan melewati SLA</dt>
                        <dd class="text-sm font-semibold text-rose-700 tabular-nums dark:text-rose-400">{{ $overdueCount }}</dd>
                    </div>
                    @if($bottleneckStage)
                        <div class="flex min-h-10 items-center justify-between gap-4 py-2">
                            <dt class="text-sm text-ink-secondary">Tahap terlama: {{ ucfirst(strtolower($bottleneckStage)) }}</dt>
                            <dd class="text-sm font-semibold text-ink tabular-nums">{{ $stageAverages[$bottleneckStage]['avg'] }} hari</dd>
                        </div>
                    @endif
                    <div class="flex min-h-10 items-center justify-between gap-4 py-2">
                        <dt class="text-sm text-ink-secondary">Rata-rata seluruh tahap</dt>
                        <dd class="text-sm font-semibold text-ink tabular-nums">{{ $avgStageDuration !== null ? round($avgStageDuration, 1) : 0 }} hari</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section aria-labelledby="attention-heading">
            <div class="ui-toolbar mb-4">
                <div>
                    <h2 id="attention-heading" class="ui-section-heading">Pekerjaan yang membutuhkan perhatian</h2>
                    <p class="ui-section-description">Pekerjaan aktif yang lama tertahan pada tahap tertentu.</p>
                </div>
                <a href="{{ route('work-orders.index') }}" wire:navigate class="ui-text-action shrink-0">
                    Semua pekerjaan
                    <span aria-hidden="true" class="ml-1">→</span>
                </a>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <caption class="sr-only">Daftar pekerjaan aktif yang membutuhkan perhatian</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nomor kontrak</th>
                            <th scope="col">Debitur dan klien</th>
                            <th scope="col">Aging status</th>
                            <th scope="col">Status</th>
                            <th scope="col">Tenggat SLA</th>
                            <th scope="col">PIC</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bottleneckJobs as $job)
                            <tr wire:key="dashboard-attention-{{ $job->id }}">
                                <td>
                                    <div class="font-mono text-sm font-semibold text-ink">{{ $job->contract_no }}</div>
                                    <div class="mt-1 text-xs text-ink-muted">{{ $job->offer?->branch?->code ?? '-' }}</div>
                                </td>
                                <td>
                                    <div class="font-medium text-ink">{{ $job->offer?->debtor?->name ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-ink-muted">{{ $job->offer?->client?->name ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="font-semibold text-rose-700 tabular-nums dark:text-rose-400">{{ $job->aging_days }} hari</span>
                                </td>
                                <td><x-status-badge :status="$job->current_status" /></td>
                                <td>
                                    <div class="text-sm text-ink tabular-nums">{{ $job->sla_date ? $job->sla_date->format('d M Y') : '-' }}</div>
                                    <x-sla-badge :overdue="$job->is_overdue" overdue-label="Terlambat" class="mt-1" />
                                </td>
                                <td class="whitespace-nowrap text-xs leading-5">
                                    <div><span class="text-ink-muted">Surveyor:</span> {{ $job->surveyors->first()?->user?->name ?? '-' }}</div>
                                    <div><span class="text-ink-muted">Reviewer:</span> {{ $job->reviewers->first()?->user?->name ?? '-' }}</div>
                                </td>
                                <td class="text-right">
                                    <a
                                        href="{{ route('work-orders.show', $job->id) }}"
                                        wire:navigate
                                        class="ui-text-action"
                                        aria-label="Buka pekerjaan {{ $job->contract_no }}"
                                    >
                                        Buka
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="ui-empty-state">Tidak ada pekerjaan aktif yang tertahan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
