@props(['overdue' => false, 'applicable' => false, 'overdueLabel' => 'OVERDUE SLA', 'okLabel' => 'SLA OK'])

@if($overdue)
    <span {{ $attributes->merge(['class' => 'ui-badge ui-badge-danger']) }}>{{ $overdueLabel }}</span>
@elseif($applicable)
    <span {{ $attributes->merge(['class' => 'ui-badge ui-badge-success']) }}>{{ $okLabel }}</span>
@endif
