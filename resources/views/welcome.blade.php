<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'KJPP Monitoring') }}</title>

        <script>
            (function() {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <div class="min-h-screen flex flex-col">
            <!-- Header -->
            <header class="border-b border-gray-200 dark:border-gray-700/80 bg-white/80 dark:bg-gray-800/80 backdrop-blur sticky top-0 z-20">
                <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
                    <div class="flex items-center gap-3 font-bold text-gray-900 dark:text-white">
                        <x-application-logo class="block h-8 w-auto fill-current text-indigo-600 dark:text-indigo-400" />
                        <span class="tracking-tight">KJPP Monitoring</span>
                    </div>
                    @if (Route::has('login'))
                        <livewire:welcome.navigation />
                    @endif
                </div>
            </header>

            <main class="flex-1">
                <!-- Hero -->
                <section class="max-w-7xl mx-auto px-6 pt-16 pb-12 lg:pt-24 lg:pb-16">
                    <div class="max-w-3xl">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
                            Operational &amp; Asset Monitoring System
                        </span>
                        <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                            Satu Platform untuk Seluruh Alur Kerja Penilaian KJPP
                        </h1>
                        <p class="mt-5 text-lg text-gray-600 dark:text-gray-300 leading-relaxed">
                            Kelola penawaran, kontrak kerja, survey lapangan, produksi laporan, hingga bukti pengiriman &mdash;
                            dengan penomoran dokumen otomatis, pelacakan SLA, dan dashboard analitik lintas cabang.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            @auth
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition">
                                    Ke Dashboard &rarr;
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition">
                                    Masuk ke Sistem &rarr;
                                </a>
                            @endauth
                        </div>
                    </div>
                </section>

                <!-- Workflow Pipeline -->
                <section class="max-w-7xl mx-auto px-6 pb-16">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 lg:p-8 border border-gray-100 dark:border-gray-700/50 shadow-sm">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-6">Alur Kerja End-to-End</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                            @php
                                $steps = [
                                    ['icon' => '📝', 'label' => 'Penawaran', 'desc' => 'Nomor otomatis per cabang'],
                                    ['icon' => '📋', 'label' => 'Kontrak Kerja', 'desc' => 'Konversi otomatis dari deal'],
                                    ['icon' => '🔎', 'label' => 'Survey & Pengerjaan', 'desc' => 'Assign PIC & tracking SLA'],
                                    ['icon' => '📄', 'label' => 'Laporan Resmi', 'desc' => 'Nomor mengikuti kontrak'],
                                    ['icon' => '📦', 'label' => 'Pengiriman', 'desc' => 'Bukti resi & penerima'],
                                ];
                            @endphp
                            @foreach($steps as $i => $step)
                                <div class="relative">
                                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 text-center space-y-1.5 h-full">
                                        <div class="w-10 h-10 mx-auto rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                                            {{ $step['icon'] }}
                                        </div>
                                        <div class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ $step['label'] }}</div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $step['desc'] }}</div>
                                    </div>
                                    @if($i < count($steps) - 1)
                                        <div class="hidden sm:flex absolute top-1/2 -right-4 -translate-y-1/2 text-gray-300 dark:text-gray-600 z-10">&rarr;</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <!-- Feature Grid -->
                <section class="max-w-7xl mx-auto px-6 pb-20">
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-3">
                            <div class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Penomoran Dokumen Otomatis</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Format nomor penawaran &amp; kontrak konsisten per cabang, bulan, dan tahun &mdash; admin cukup input nomor urut.</p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-3">
                            <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Pelacakan SLA &amp; Status</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Funnel status Persiapan &rarr; Survey &rarr; Pengerjaan &rarr; Review &rarr; Cetak &rarr; Selesai, lengkap dengan deteksi overdue.</p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-3">
                            <div class="w-11 h-11 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Laporan Produksi &amp; Export Excel</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Rekap lintas cabang siap diekspor ke Excel terformat &mdash; 5 kelompok data, siap diaudit atau dilaporkan ke manajemen.</p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-3">
                            <div class="w-11 h-11 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Dashboard Analitik</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Tren pendapatan, konversi penawaran, dan analisis bottleneck per tahap &mdash; per cabang atau seluruh perusahaan.</p>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="border-t border-gray-200 dark:border-gray-700/80 py-8 text-center text-xs text-gray-400 dark:text-gray-500">
                &copy; {{ date('Y') }} KJPP Monitoring &mdash; Sistem Internal Operasional &amp; Aset.
            </footer>
        </div>
    </body>
</html>
