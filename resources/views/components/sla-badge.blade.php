@props(['overdue' => false, 'applicable' => false, 'overdueLabel' => 'OVERDUE SLA', 'okLabel' => 'SLA OK'])

@if($overdue)
    <span {{ $attributes->merge(['class' => 'inline-block px-2 py-0.5 bg-rose-100 dark:bg-rose-900/60 text-rose-700 dark:text-rose-300 font-bold rounded text-[10px]']) }}>{{ $overdueLabel }}</span>
@elseif($applicable)
    <span {{ $attributes->merge(['class' => 'inline-block px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold rounded text-[10px]']) }}>{{ $okLabel }}</span>
@endif
