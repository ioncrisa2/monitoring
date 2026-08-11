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

<div>
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div x-cloak
         x-show="sidebarOpen" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-gray-900/60 backdrop-blur-sm md:hidden"></div>

    <!-- Mobile Sidebar Drawer -->
    <aside x-cloak
           x-show="sidebarOpen"
           x-transition:enter="transition ease-in-out duration-300 transform"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in-out duration-300 transform"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700/80 md:hidden flex flex-col justify-between shadow-2xl">
        
        <div>
            <!-- Brand Logo Header -->
            <div class="h-16 flex items-center justify-between px-5 border-b border-gray-100 dark:border-gray-700/80">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 font-bold text-gray-900 dark:text-white text-lg">
                    <x-application-logo class="block h-8 w-auto fill-current text-indigo-600 dark:text-indigo-400" />
                    <span>KJPP Monitoring</span>
                </a>
                <button @click="sidebarOpen = false" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg cursor-pointer">&times;</button>
            </div>

            <!-- Mobile Navigation Links -->
            <nav class="p-4 space-y-6 overflow-y-auto">
                <div class="space-y-1">
                    <div class="px-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Utama</div>

                    @can('menu.dashboard')
                        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            <span>Dashboard</span>
                        </a>
                    @endcan

                    @can('menu.offers')
                        <a href="{{ route('offers.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('offers.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Penawaran</span>
                        </a>
                    @endcan

                    @can('menu.work-orders')
                        <a href="{{ route('work-orders.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('work-orders.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span>Pekerjaan / Job</span>
                        </a>
                    @endcan

                    @can('menu.reports')
                        <a href="{{ route('reports.production') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('reports.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Laporan Produksi</span>
                        </a>
                    @endcan

                    @can('menu.imports')
                        <a href="{{ route('imports.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('imports.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <span>Impor Data</span>
                        </a>
                    @endcan
                </div>

                @canany(['menu.master-data', 'menu.master-users', 'menu.audit-logs'])
                    <div class="space-y-1">
                        <div class="px-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Master Data & System</div>

                        @can('menu.master-data')
                            <a href="{{ route('master.branches') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.branches') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0v-5a2 2 0 012-2h2a2 2 0 012 2v5m-4 0h4"/></svg>
                                <span>Cabang</span>
                            </a>
                        @endcan

                        @can('menu.master-users')
                            <a href="{{ route('master.users') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.users') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                <span>Pengguna Sistem</span>
                            </a>
                            <a href="{{ route('master.roles-permissions') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.roles-permissions') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Role & Hak Akses</span>
                            </a>
                        @endcan

                        @can('menu.master-data')
                            <a href="{{ route('master.organizations') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.organizations') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                                <span>Pemberi Tugas / Klien</span>
                            </a>
                            <a href="{{ route('master.debtors') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.debtors') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span>Debitur</span>
                            </a>
                        @endcan

                        @can('menu.audit-logs')
                            <a href="{{ route('audit-logs.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('audit-logs.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                <span>Audit Trail & Logs</span>
                            </a>
                        @endcan
                    </div>
                @endcanany
            </nav>
        </div>

        <!-- Footer User info -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-700/80">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span class="font-medium truncate">{{ auth()->user()->name }}</span>
                <span class="uppercase font-bold text-[10px] px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-indigo-600 dark:text-indigo-400">{{ auth()->user()->roles->first()?->name ?? 'User' }}</span>
            </div>
        </div>
    </aside>

    <!-- Desktop Desktop Fixed Sidebar (w-64) -->
    <aside class="hidden md:flex md:flex-col md:w-64 md:fixed md:inset-y-0 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700/80 z-30 justify-between">
        <div>
            <!-- Brand Logo Header -->
            <div class="h-16 flex items-center px-6 border-b border-gray-100 dark:border-gray-700/80">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 font-bold text-gray-900 dark:text-white text-lg">
                    <x-application-logo class="block h-8 w-auto fill-current text-indigo-600 dark:text-indigo-400" />
                    <span class="tracking-tight">KJPP Monitoring</span>
                </a>
            </div>

            <!-- Desktop Navigation Links -->
            <nav class="p-4 space-y-6 overflow-y-auto max-h-[calc(100vh-8rem)]">
                <div class="space-y-1">
                    <div class="px-3 text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Utama</div>

                    @can('menu.dashboard')
                        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            <span>Dashboard</span>
                        </a>
                    @endcan

                    @can('menu.offers')
                        <a href="{{ route('offers.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('offers.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Penawaran</span>
                        </a>
                    @endcan

                    @can('menu.work-orders')
                        <a href="{{ route('work-orders.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('work-orders.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span>Pekerjaan / Job</span>
                        </a>
                    @endcan

                    @can('menu.reports')
                        <a href="{{ route('reports.production') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('reports.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Laporan Produksi</span>
                        </a>
                    @endcan

                    @can('menu.imports')
                        <a href="{{ route('imports.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('imports.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <span>Impor Data</span>
                        </a>
                    @endcan
                </div>

                @canany(['menu.master-data', 'menu.master-users', 'menu.audit-logs'])
                    <div class="space-y-1">
                        <div class="px-3 text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Master Data & System</div>

                        @can('menu.master-data')
                            <a href="{{ route('master.branches') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.branches') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0v-5a2 2 0 012-2h2a2 2 0 012 2v5m-4 0h4"/></svg>
                                <span>Cabang</span>
                            </a>
                        @endcan

                        @can('menu.master-users')
                            <a href="{{ route('master.users') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.users') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                <span>Pengguna Sistem</span>
                            </a>
                            <a href="{{ route('master.roles-permissions') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.roles-permissions') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Role & Hak Akses</span>
                            </a>
                        @endcan

                        @can('menu.master-data')
                            <a href="{{ route('master.organizations') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.organizations') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                                <span>Pemberi Tugas / Klien</span>
                            </a>
                            <a href="{{ route('master.debtors') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('master.debtors') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span>Debitur</span>
                            </a>
                        @endcan

                        @can('menu.audit-logs')
                            <a href="{{ route('audit-logs.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('audit-logs.*') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                <span>Audit Trail & Logs</span>
                            </a>
                        @endcan
                    </div>
                @endcanany
            </nav>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/30">
            <div class="flex items-center justify-between text-xs">
                <div class="truncate font-semibold text-gray-800 dark:text-gray-200">
                    {{ auth()->user()->name }}
                </div>
                <span class="uppercase font-bold text-[10px] px-2 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300">
                    {{ auth()->user()->roles->first()?->name ?? 'User' }}
                </span>
            </div>
        </div>
    </aside>

    <!-- Top Header Bar (md:pl-64) -->
    <header class="md:pl-64 sticky top-0 z-20 bg-white/80 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700/80 backdrop-blur">
        <div class="h-16 flex items-center justify-between px-3 sm:px-6">
            <!-- Left: Mobile Toggle & Page Context -->
            <div class="flex items-center gap-2 sm:gap-4 min-w-0">
                <button @click="sidebarOpen = !sidebarOpen" type="button" class="md:hidden p-2 rounded-lg text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none cursor-pointer shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="text-sm font-semibold text-gray-700 dark:text-gray-300 hidden md:block">
                    KJPP Operational & Asset Monitoring System
                </div>
            </div>

            <!-- Right: User Dropdown with Theme Switcher -->
            <div class="flex items-center gap-2 shrink-0">
                <x-dropdown align="right" width="72" contentClasses="py-1 bg-white dark:bg-gray-800 rounded-2xl shadow-xl ring-1 ring-black/5 dark:ring-white/10 divide-y divide-gray-100 dark:divide-gray-700/70">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100/80 hover:bg-gray-200/70 dark:bg-gray-700/60 dark:hover:bg-gray-700 border border-gray-200/80 dark:border-gray-600/60 focus:outline-none transition cursor-pointer shadow-sm">
                            <div class="w-6 h-6 rounded-full bg-indigo-600 dark:bg-indigo-500 text-white font-bold flex items-center justify-center text-xs shrink-0 shadow-xs">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                            <span class="font-semibold text-xs sm:text-sm max-w-[100px] sm:max-w-[150px] truncate">{{ auth()->user()->name }}</span>
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <!-- User Info Header -->
                        <div class="px-4 py-3">
                            <div class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ auth()->user()->email }}</div>
                            <div class="mt-2 inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-wider bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
                                {{ auth()->user()->roles->first()?->name ?? 'User' }}
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="py-1">
                            <x-dropdown-link :href="route('profile')" wire:navigate class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100/80 dark:hover:bg-gray-700/60 transition">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span>Profil Akun</span>
                            </x-dropdown-link>
                        </div>

                        <!-- Theme Switcher (Filament Style 3 Icons) -->
                        <div x-data="{
                            theme: localStorage.getItem('theme') || 'system',
                            setTheme(val) {
                                this.theme = val;
                                if (val === 'system') {
                                    localStorage.removeItem('theme');
                                    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                                        document.documentElement.classList.add('dark');
                                    } else {
                                        document.documentElement.classList.remove('dark');
                                    }
                                } else if (val === 'dark') {
                                    localStorage.setItem('theme', 'dark');
                                    document.documentElement.classList.add('dark');
                                } else {
                                    localStorage.setItem('theme', 'light');
                                    document.documentElement.classList.remove('dark');
                                }
                            }
                        }" class="p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5 px-0.5">Tema Tampilan</div>
                            <div class="flex items-center p-1 rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200/80 dark:border-gray-700/60">
                                <!-- Ikuti Sistem -->
                                <button type="button" 
                                        @click="setTheme('system')"
                                        :class="theme === 'system' ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                        class="flex-1 inline-flex items-center justify-center p-1.5 rounded-lg transition cursor-pointer"
                                        title="Ikuti Sistem">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </button>

                                <!-- Terang -->
                                <button type="button" 
                                        @click="setTheme('light')"
                                        :class="theme === 'light' ? 'bg-white dark:bg-gray-700 text-amber-500 dark:text-amber-400 shadow-sm' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                        class="flex-1 inline-flex items-center justify-center p-1.5 rounded-lg transition cursor-pointer"
                                        title="Terang">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                </button>

                                <!-- Gelap -->
                                <button type="button" 
                                        @click="setTheme('dark')"
                                        :class="theme === 'dark' ? 'bg-white dark:bg-gray-700 text-indigo-500 dark:text-indigo-400 shadow-sm' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                        class="flex-1 inline-flex items-center justify-center p-1.5 rounded-lg transition cursor-pointer"
                                        title="Gelap">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Logout -->
                        <div class="py-1">
                            <button wire:click="logout" class="w-full text-start">
                                <x-dropdown-link class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    <span>Log Out</span>
                                </x-dropdown-link>
                            </button>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </header>
</div>
