<nav class="flex items-center gap-2" aria-label="Navigasi akun">
    @auth
        @can('menu.dashboard')
            <a href="{{ route('dashboard') }}" wire:navigate class="ui-btn ui-btn-primary">Dashboard</a>
        @else
            <a href="{{ route('profile') }}" wire:navigate class="ui-btn ui-btn-secondary">Profil</a>
        @endcan
    @else
        <a href="{{ route('login') }}" wire:navigate class="ui-btn ui-btn-primary">Masuk</a>

    @endauth
</nav>
