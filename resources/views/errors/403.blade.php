<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>403 · Akses Ditolak | {{ config('app.name', 'KJPP Monitoring') }}</title>

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
        <main class="flex min-h-dvh items-center justify-center px-4 py-10">
            <section class="ui-surface w-full max-w-lg px-6 py-8 text-center sm:px-8" aria-labelledby="access-denied-heading">
                <x-application-logo class="mx-auto size-10 text-brand" />
                <p class="mt-6 text-sm font-semibold text-rose-700 dark:text-rose-400">Error 403</p>
                <h1 id="access-denied-heading" class="mt-2 text-2xl font-semibold tracking-tight text-ink">Akses tidak tersedia</h1>
                <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-ink-secondary">
                    {{ $exception->getMessage() ?: 'Akun Anda tidak memiliki hak akses yang diperlukan untuk membuka halaman atau menjalankan tindakan ini.' }}
                </p>

                <div class="mt-7">
                    @auth
                        @can('menu.dashboard')
                            <a href="{{ route('dashboard') }}" class="ui-btn ui-btn-primary w-full sm:w-auto">Kembali ke dashboard</a>
                        @else
                            <a href="{{ route('profile') }}" class="ui-btn ui-btn-primary w-full sm:w-auto">Buka profil</a>
                        @endcan
                    @else
                        <a href="{{ route('login') }}" class="ui-btn ui-btn-primary w-full sm:w-auto">Masuk ke sistem</a>
                    @endauth
                </div>
            </section>
        </main>
    </body>
</html>
