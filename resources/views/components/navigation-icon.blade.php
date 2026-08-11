@props(['name'])

<svg aria-hidden="true" {{ $attributes->class(['h-5 w-5 shrink-0']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    @switch($name)
        @case('dashboard')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2 7-7 7 7 2 2M5 10v10h5v-5h4v5h5V10" />
            @break
        @case('offer')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 3h7l4 4v14H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5M9 12h6M9 16h6" />
            @break
        @case('work')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 6V4h6v2m-3 6h.01M4 7h16a1 1 0 011 1v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a1 1 0 011-1zm-1 5a23.9 23.9 0 0018 0" />
            @break
        @case('report')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 3h7l4 4v14H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5M9 17v-2m3 2v-5m3 5v-8" />
            @break
        @case('import')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
            @break
        @case('branch')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 21V5a2 2 0 012-2h8a2 2 0 012 2v16M3 21h18M9 7h1m4 0h1M9 11h1m4 0h1M9 15h6v6" />
            @break
        @case('users')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m7-10a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87m-2-11.96a4 4 0 010 7.75" />
            @break
        @case('permissions')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 3l8 3v5c0 5-3.4 8.7-8 10-4.6-1.3-8-5-8-10V6l8-3zm-3 9 2 2 4-4" />
            @break
        @case('client')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 21h18M5 9h14M7 9v9m4-9v9m4-9v9m4-9v9M4 6l8-3 8 3v3H4V6z" />
            @break
        @case('debtor')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 11a4 4 0 100-8 4 4 0 000 8zm7 10a7 7 0 00-14 0" />
            @break
        @case('audit')
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 016 0v2H9V5zm1 7h6m-6 4h4" />
            @break
    @endswitch
</svg>
