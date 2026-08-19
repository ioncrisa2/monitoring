<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HJAR Flows') }}</title>

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
        <main class="flex min-h-dvh items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-md">
                <a href="/" wire:navigate class="mx-auto mb-6 flex w-fit items-center gap-3 rounded-ui-sm focus-visible:outline-offset-4" aria-label="Kembali ke beranda HJAR Flows">
                    <x-application-logo class="size-10 text-brand" />
                    <span>
                        <span class="block text-base font-semibold leading-5 text-ink">HJAR Flows</span>
                        <span class="block text-xs leading-5 text-ink-muted">Sistem operasional internal</span>
                    </span>
                </a>

                <div class="ui-surface px-6 py-7 sm:px-8 sm:py-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs leading-5 text-ink-muted">
                    Akses terbatas untuk pengguna yang berwenang.
                </p>
            </div>
        </main>
    </body>
</html>
