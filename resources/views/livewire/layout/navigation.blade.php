<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    [$contextGroup, $contextPage] = match (true) {
        request()->routeIs('dashboard') => ['Ringkasan', 'Dashboard'],
        request()->routeIs('offers.*') => ['Operasional', 'Penawaran'],
        request()->routeIs('work-orders.*') => ['Operasional', 'Pekerjaan'],
        request()->routeIs('reports.*') => ['Operasional', 'Laporan Produksi'],
        request()->routeIs('imports.*') => ['Operasional', 'Impor Data'],
        request()->routeIs('master.branches') => ['Administrasi', 'Cabang'],
        request()->routeIs('master.users') => ['Administrasi', 'Pengguna'],
        request()->routeIs('master.roles-permissions') => ['Administrasi', 'Peran & Hak Akses'],
        request()->routeIs('master.organizations') => ['Administrasi', 'Klien'],
        request()->routeIs('master.debtors') => ['Administrasi', 'Debitur'],
        request()->routeIs('audit-logs.*') => ['Administrasi', 'Jejak Audit'],
        request()->routeIs('profile') => ['Akun', 'Profil'],
        default => ['KJPP Monitoring', 'Aplikasi'],
    };
@endphp

<div>
    <div
        x-cloak
        x-show="sidebarOpen"
        x-trap.inert.noscroll="sidebarOpen"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-gray-950/55 md:hidden"
        aria-hidden="true"
    ></div>

    <aside
        id="mobile-navigation"
        x-cloak
        x-show="sidebarOpen"
        x-transition:enter="transition-transform ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-50 flex w-60 flex-col border-r border-line bg-surface shadow-raised md:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Navigasi aplikasi"
    >
        <div class="flex min-h-0 flex-1 flex-col">
            <div class="flex h-[3.75rem] shrink-0 items-center justify-between border-b border-line px-4">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5 text-base font-semibold tracking-tight text-ink">
                    <x-application-logo class="block h-7 w-auto fill-current text-brand" />
                    <span>KJPP Monitoring</span>
                </a>

                <button @click="sidebarOpen = false" type="button" class="ui-icon-btn h-9 w-9" aria-label="Tutup navigasi">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <x-app-navigation-links
                label="Navigasi utama seluler"
                @click="if ($event.target.closest('a')) sidebarOpen = false"
                class="min-h-0 flex-1 overflow-y-auto p-3"
            />
        </div>

        <div class="shrink-0 border-t border-line px-4 py-3">
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</div>
                <div class="mt-0.5 truncate text-xs text-ink-muted">{{ auth()->user()->roles->first()?->name ?? 'Pengguna' }}</div>
            </div>
        </div>
    </aside>

    <aside class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-line bg-surface md:flex" aria-label="Navigasi aplikasi">
        <div class="flex min-h-0 flex-1 flex-col">
            <div class="flex h-[3.75rem] shrink-0 items-center border-b border-line px-5">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5 text-base font-semibold tracking-tight text-ink">
                    <x-application-logo class="block h-7 w-auto fill-current text-brand" />
                    <span>KJPP Monitoring</span>
                </a>
            </div>

            <x-app-navigation-links class="min-h-0 flex-1 overflow-y-auto p-3" />
        </div>

        <div class="shrink-0 border-t border-line bg-surface-subtle px-4 py-3">
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</div>
                <div class="mt-0.5 truncate text-xs text-ink-muted">{{ auth()->user()->roles->first()?->name ?? 'Pengguna' }}</div>
            </div>
        </div>
    </aside>

    <header class="sticky top-0 z-20 border-b border-line bg-surface/95 backdrop-blur md:pl-60">
        <div class="flex h-[3.75rem] items-center justify-between gap-4 px-3 sm:px-6">
            <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    type="button"
                    class="ui-icon-btn md:hidden"
                    :aria-expanded="sidebarOpen"
                    aria-controls="mobile-navigation"
                    aria-label="Buka navigasi"
                >
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>

                <nav aria-label="Konteks halaman" class="hidden min-w-0 items-center gap-2 text-sm sm:flex">
                    <span class="truncate text-ink-muted">{{ $contextGroup }}</span>
                    <svg aria-hidden="true" class="h-3.5 w-3.5 shrink-0 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="m9 18 6-6-6-6" />
                    </svg>
                    <span class="truncate font-medium text-ink-secondary">{{ $contextPage }}</span>
                </nav>
            </div>

            <div class="shrink-0">
                <x-dropdown align="right" width="72" contentClasses="divide-y divide-line py-1">
                    <x-slot name="trigger">
                        <button
                            type="button"
                            class="ui-btn ui-btn-secondary min-h-9 gap-2 px-2.5 py-1.5"
                            :aria-expanded="open"
                            aria-haspopup="menu"
                            aria-label="Buka menu akun"
                        >
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-ui-sm bg-brand text-xs font-semibold text-white">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span class="max-w-[8rem] truncate text-xs sm:max-w-[10rem] sm:text-sm">{{ auth()->user()->name }}</span>
                            <svg aria-hidden="true" class="h-4 w-4 shrink-0 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-3">
                            <div class="truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</div>
                            <div class="mt-0.5 truncate text-xs text-ink-muted">{{ auth()->user()->email }}</div>
                            <div class="mt-2">
                                <span class="ui-badge ui-badge-workflow">{{ auth()->user()->roles->first()?->name ?? 'Pengguna' }}</span>
                            </div>
                        </div>

                        <div class="py-1">
                            <x-dropdown-link :href="route('profile')" wire:navigate class="flex items-center gap-2.5">
                                <svg aria-hidden="true" class="h-4 w-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 11a4 4 0 100-8 4 4 0 000 8zm7 10a7 7 0 00-14 0" />
                                </svg>
                                <span>Profil akun</span>
                            </x-dropdown-link>
                        </div>

                        <div
                            x-data="{
                                theme: localStorage.getItem('theme') || 'system',
                                setTheme(value) {
                                    this.theme = value;

                                    if (value === 'system') {
                                        localStorage.removeItem('theme');
                                        document.documentElement.classList.toggle('dark', window.matchMedia('(prefers-color-scheme: dark)').matches);
                                        return;
                                    }

                                    localStorage.setItem('theme', value);
                                    document.documentElement.classList.toggle('dark', value === 'dark');
                                }
                            }"
                            class="p-3"
                        >
                            <div class="mb-2 text-xs font-medium text-ink-muted">Tema tampilan</div>
                            <div class="grid grid-cols-3 gap-1 rounded-ui border border-line bg-surface-subtle p-1">
                                <button
                                    type="button"
                                    @click="setTheme('system')"
                                    :aria-pressed="theme === 'system'"
                                    :class="theme === 'system' ? 'border-line bg-surface text-brand' : 'border-transparent text-ink-muted hover:text-ink'"
                                    class="inline-flex min-h-8 items-center justify-center rounded-ui-sm border transition duration-200"
                                    aria-label="Ikuti tema sistem"
                                >
                                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 4h14a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zm4 16h6m-3-3v3" /></svg>
                                </button>
                                <button
                                    type="button"
                                    @click="setTheme('light')"
                                    :aria-pressed="theme === 'light'"
                                    :class="theme === 'light' ? 'border-line bg-surface text-brand' : 'border-transparent text-ink-muted hover:text-ink'"
                                    class="inline-flex min-h-8 items-center justify-center rounded-ui-sm border transition duration-200"
                                    aria-label="Gunakan tema terang"
                                >
                                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.42 1.41M7.05 16.95l-1.41 1.41M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                </button>
                                <button
                                    type="button"
                                    @click="setTheme('dark')"
                                    :aria-pressed="theme === 'dark'"
                                    :class="theme === 'dark' ? 'border-line bg-surface text-brand' : 'border-transparent text-ink-muted hover:text-ink'"
                                    class="inline-flex min-h-8 items-center justify-center rounded-ui-sm border transition duration-200"
                                    aria-label="Gunakan tema gelap"
                                >
                                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 15.5A8.5 8.5 0 018.5 4 8.5 8.5 0 1020 15.5z" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="py-1">
                            <button type="button" wire:click="logout" class="ui-dropdown-link flex items-center gap-2.5 text-red-700 dark:text-red-400">
                                <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M14 8V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2h7a2 2 0 002-2v-3m-2-4h11m0 0-3-3m3 3-3 3" />
                                </svg>
                                <span>Keluar</span>
                            </button>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </header>
</div>
