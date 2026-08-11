<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'KJPP Monitoring') }}</title>
        <meta name="description" content="Sistem internal untuk pemantauan penawaran, pekerjaan penilaian, SLA, dan laporan produksi KJPP.">

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

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas font-sans text-ink antialiased">
        <a href="#welcome-content" class="sr-only fixed left-4 top-4 z-50 rounded-ui bg-brand px-4 py-2 text-sm font-semibold text-white focus:not-sr-only">
            Lewati ke konten utama
        </a>

        <div class="flex min-h-dvh flex-col">
            <header class="border-b border-line bg-surface">
                <div class="mx-auto flex h-16 w-full max-w-app items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <a href="/" class="flex items-center gap-3 rounded-ui-sm focus-visible:outline-offset-4" aria-label="Beranda KJPP Monitoring">
                        <x-application-logo class="size-9 text-brand" />
                        <span class="text-base font-semibold text-ink">KJPP Monitoring</span>
                    </a>

                    @if(Route::has('login'))
                        <livewire:welcome.navigation />
                    @endif
                </div>
            </header>

            <main id="welcome-content" tabindex="-1" class="flex flex-1 items-center">
                <section class="mx-auto w-full max-w-app px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand">Sistem operasional internal</p>
                        <h1 class="mt-4 text-4xl font-semibold leading-tight tracking-tight text-ink sm:text-5xl">
                            Satu tempat untuk memantau pekerjaan penilaian.
                        </h1>
                        <p class="mt-5 max-w-2xl text-base leading-7 text-ink-secondary sm:text-lg">
                            Kelola penawaran, pekerjaan, SLA, laporan resmi, dan pengiriman dengan alur yang konsisten lintas cabang.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @auth
                                @can('menu.dashboard')
                                    <a href="{{ route('dashboard') }}" wire:navigate class="ui-btn ui-btn-primary w-full sm:w-auto">
                                        Buka dashboard
                                        <span aria-hidden="true">→</span>
                                    </a>
                                @else
                                    <a href="{{ route('profile') }}" wire:navigate class="ui-btn ui-btn-primary w-full sm:w-auto">Buka profil</a>
                                @endcan
                            @else
                                <a href="{{ route('login') }}" wire:navigate class="ui-btn ui-btn-primary w-full sm:w-auto">
                                    Masuk ke sistem
                                    <span aria-hidden="true">→</span>
                                </a>
                                @if(Route::has('register'))
                                    <a href="{{ route('register') }}" wire:navigate class="ui-btn ui-btn-secondary w-full sm:w-auto">Daftar akun</a>
                                @endif
                            @endauth
                        </div>

                        <ul class="mt-12 flex flex-wrap gap-x-6 gap-y-2 border-y border-line py-4 text-sm text-ink-muted" aria-label="Cakupan sistem">
                            <li>Penawaran</li>
                            <li>Pekerjaan dan SLA</li>
                            <li>Laporan produksi</li>
                            <li>Jejak audit</li>
                        </ul>
                    </div>
                </section>
            </main>

            <footer class="border-t border-line px-4 py-5 text-center text-xs text-ink-muted">
                &copy; {{ date('Y') }} KJPP Monitoring · Sistem internal operasional dan aset
            </footer>
        </div>
    </body>
</html>
