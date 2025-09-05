@aware([ 'placeholder' ])

@props([
    'placeholder' => null,
    'clearable' => null,
    'invalid' => false,
    'size' => null,
])

@php

$classes = Flux::classes()
    ->add('group/select-button cursor-default py-2')
    ->add('overflow-hidden') // Overflow hidden is here to prevent the button from growing if the selected date text is too long.
    ->add('flex items-center')
    ->add('shadow-xs')
    ->add('bg-white dark:bg-white/10 dark:disabled:bg-white/7')
    // Make the placeholder match the text color of standard input placeholders...
    ->add('disabled:shadow-none')
    ->add(match ($size) {
        default => 'h-10 text-base sm:text-sm rounded-lg px-3 block w-full',
        'sm' => 'h-8 text-sm rounded-md ps-3 pe-2 block w-full',
        'xs' => 'h-6 text-xs rounded-md ps-3 pe-2 block w-full',
    })
    ->add($invalid
        ? 'border border-red-500'
        : 'border border-zinc-200 border-b-zinc-300/80 dark:border-white/10'
    )
    ;
@endphp

<button type="button" {{ $attributes->class($classes) }} @if ($invalid) data-invalid @endif data-flux-group-target data-flux-date-picker-button>
    <flux:icon.calendar variant="mini" class="me-2 text-zinc-300 [[data-flux-date-picker-button]:hover_&]:text-zinc-800 in-[[disabled]]:text-zinc-200! dark:text-white/60 dark:[[data-flux-date-picker-button]:hover_&]:text-white dark:in-[[disabled]]:text-white/40! [[data-flux-date-picker-button][data-open]_&]:text-zinc-800 dark:[[data-flux-date-picker-button][data-open]_&]:text-white" />

    <?php if ($slot->isNotEmpty()): ?>
        {{ $slot }}
    <?php else: ?>
        <flux:date-picker.selected :$placeholder />
    <?php endif; ?>

    <?php if ($clearable): ?>
        <flux:button as="div"
            class="cursor-pointer ms-2 {{ $size === 'sm' || $size === 'xs' ? '-me-1' : '-me-2' }} [[data-flux-date-picker-button]:has([data-flux-date-picker-placeholder])_&]:hidden [[data-flux-select]:has([disabled])_&]:hidden"
            variant="subtle"
            :size="$size === 'sm' || $size === 'xs' ? 'xs' : 'sm'"
            square
            tabindex="-1"
            aria-label="Clear date"
            x-on:click.prevent.stop="let datePicker = $el.closest('ui-date-picker'); datePicker.clear();"
        >
            <flux:icon.x-mark variant="micro" />
        </flux:button>
    <?php endif; ?>
</button>
