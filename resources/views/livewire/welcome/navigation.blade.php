<nav class="flex items-center gap-2">
    @auth
        <a
            href="{{ url('/dashboard') }}"
            class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 ring-1 ring-transparent transition hover:text-indigo-600 hover:bg-indigo-50 focus:outline-none focus-visible:ring-indigo-500 dark:text-gray-300 dark:hover:text-indigo-400 dark:hover:bg-indigo-950/40"
        >
            Dashboard
        </a>
    @else
        <a
            href="{{ route('login') }}"
            class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 ring-1 ring-transparent transition hover:text-indigo-600 hover:bg-indigo-50 focus:outline-none focus-visible:ring-indigo-500 dark:text-gray-300 dark:hover:text-indigo-400 dark:hover:bg-indigo-950/40"
        >
            Log in
        </a>

        @if (Route::has('register'))
            <a
                href="{{ route('register') }}"
                class="rounded-lg px-3 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white ring-1 ring-transparent transition focus:outline-none focus-visible:ring-indigo-500"
            >
                Register
            </a>
        @endif
    @endauth
</nav>
