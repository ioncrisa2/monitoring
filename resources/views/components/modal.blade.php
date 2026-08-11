@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'closeProperty' => null,
    'labelledby' => null,
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-[28rem]',
    'md' => 'sm:max-w-[36rem]',
    'lg' => 'sm:max-w-[40rem]',
    'xl' => 'sm:max-w-[45rem]',
    '2xl' => 'sm:max-w-[50rem]',
][$maxWidth];
@endphp

<div
    x-data="{
        show: @js($show),
        closeModal() {
            this.show = false

            if (@js($closeProperty)) {
                $wire.set(@js($closeProperty), false)
            }
        },
        focusables() {
            // All focusable element types...
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                // All non-disabled elements...
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-init="
        const syncVisibility = value => {
            if (value) {
                document.body.classList.add('overflow-y-hidden');
                {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable()?.focus(), 100)' : '' }}
            } else {
                document.body.classList.remove('overflow-y-hidden');
            }
        }

        syncVisibility(show)
        $watch('show', syncVisibility)
    "
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? closeModal() : null"
    x-on:close.stop="closeModal()"
    x-on:keydown.escape.window="closeModal()"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div
        x-show="show"
        class="fixed inset-0 transform transition-all"
        x-on:click="closeModal()"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="ui-modal-backdrop absolute inset-0"></div>
    </div>

    <div
        x-show="show"
        class="ui-modal-panel mb-6 overflow-hidden transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto"
        role="dialog"
        aria-modal="true"
        @if($labelledby) aria-labelledby="{{ $labelledby }}" @endif
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{ $slot }}
    </div>
</div>
