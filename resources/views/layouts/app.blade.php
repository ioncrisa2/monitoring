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

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas font-sans text-ink antialiased">
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-[60] rounded-ui bg-brand px-4 py-2 text-sm font-semibold text-white focus:not-sr-only">
            Lewati ke konten utama
        </a>

        <div
            x-data="{ sidebarOpen: false }"
            x-init="
                const desktopQuery = window.matchMedia('(min-width: 768px)');
                desktopQuery.addEventListener('change', event => { if (event.matches) sidebarOpen = false });
            "
            @keydown.escape.window="sidebarOpen = false"
            class="min-h-screen bg-canvas"
        >
            <livewire:layout.navigation />

            <!-- Main Content Area (Offset by md:pl-60 for Fixed Desktop Sidebar) -->
            <div class="flex min-h-[calc(100dvh-3.75rem)] flex-col md:pl-60">
                @if (isset($header))
                    <header class="border-b border-line bg-surface">
                        <div class="mx-auto max-w-app px-4 py-5 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main id="main-content" tabindex="-1" class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
