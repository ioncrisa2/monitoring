@props(['label' => 'Navigasi utama'])

<nav aria-label="{{ $label }}" {{ $attributes->class(['space-y-6']) }}>
    @can('menu.dashboard')
        <x-sidebar-link :href="route('dashboard')" wire:navigate :active="request()->routeIs('dashboard')">
            <x-navigation-icon name="dashboard" />
            <span>Dashboard</span>
        </x-sidebar-link>
    @endcan

    <div class="space-y-1">
        <div class="ui-nav-section-label">Operasional</div>

        @can('menu.offers')
            <x-sidebar-link :href="route('offers.index')" wire:navigate :active="request()->routeIs('offers.*')">
                <x-navigation-icon name="offer" />
                <span>Penawaran</span>
            </x-sidebar-link>
        @endcan

        @can('menu.work-orders')
            <x-sidebar-link :href="route('work-orders.index')" wire:navigate :active="request()->routeIs('work-orders.*')">
                <x-navigation-icon name="work" />
                <span>Pekerjaan</span>
            </x-sidebar-link>
        @endcan

        @can('menu.reports')
            <x-sidebar-link :href="route('reports.production')" wire:navigate :active="request()->routeIs('reports.*')">
                <x-navigation-icon name="report" />
                <span>Laporan Produksi</span>
            </x-sidebar-link>
        @endcan

        @can('menu.imports')
            <x-sidebar-link :href="route('imports.index')" wire:navigate :active="request()->routeIs('imports.*')">
                <x-navigation-icon name="import" />
                <span>Impor Data</span>
            </x-sidebar-link>
        @endcan
    </div>

    @canany(['menu.master-data', 'menu.master-users', 'menu.audit-logs'])
        <div class="space-y-1">
            <div class="ui-nav-section-label">Administrasi</div>

            @can('menu.master-data')
                <x-sidebar-link :href="route('master.branches')" wire:navigate :active="request()->routeIs('master.branches')">
                    <x-navigation-icon name="branch" />
                    <span>Cabang</span>
                </x-sidebar-link>
            @endcan

            @can('menu.master-users')
                <x-sidebar-link :href="route('master.users')" wire:navigate :active="request()->routeIs('master.users')">
                    <x-navigation-icon name="users" />
                    <span>Pengguna</span>
                </x-sidebar-link>

                <x-sidebar-link :href="route('master.roles-permissions')" wire:navigate :active="request()->routeIs('master.roles-permissions')">
                    <x-navigation-icon name="permissions" />
                    <span>Peran & Hak Akses</span>
                </x-sidebar-link>
            @endcan

            @can('menu.master-data')
                <x-sidebar-link :href="route('master.organizations')" wire:navigate :active="request()->routeIs('master.organizations')">
                    <x-navigation-icon name="client" />
                    <span>Klien</span>
                </x-sidebar-link>

                <x-sidebar-link :href="route('master.debtors')" wire:navigate :active="request()->routeIs('master.debtors')">
                    <x-navigation-icon name="debtor" />
                    <span>Debitur</span>
                </x-sidebar-link>
            @endcan

            @can('menu.audit-logs')
                <x-sidebar-link :href="route('audit-logs.index')" wire:navigate :active="request()->routeIs('audit-logs.*')">
                    <x-navigation-icon name="audit" />
                    <span>Jejak Audit</span>
                </x-sidebar-link>
            @endcan
        </div>
    @endcanany
</nav>
