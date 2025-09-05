@aware([ 'placeholder' ])

@props([
    'placeholder' => null,
    'clearable' => false,
    'invalid' => null,
    'size' => null,
])

{{-- For Firefox, we need to reset the inputs padding back to the default as if there is no trailing icon, so the native date input calendar icon is correctly positioned... --}}
<flux:input type="date" :$invalid :$size :$placeholder :$attributes class:input="[@supports(-moz-appearance:none)]:pe-3">
    <x-slot name="iconTrailing">
        <?php if ($clearable): ?>
            <div class="absolute top-0 bottom-0 flex items-center justify-center pe-10 end-0">
                <flux:input.clearable :$size as="div" />
            </div>
        <?php endif; ?>

        {{-- Hide this button on Firefox because we can't get rid of the default date input calendar icon, so hide ours instead... --}}
        <flux:button size="sm" square variant="subtle" class="-me-1 [@supports(-moz-appearance:none)]:hidden in-[[disabled]]:pointer-events-none [&:hover>*]:text-zinc-800 dark:[&:hover>*]:text-white">
            <flux:icon.calendar variant="mini" class="text-zinc-300 in-[[disabled]]:text-zinc-200! dark:text-white/60 dark:in-[[disabled]]:text-white/40!" />
        </flux:button>

        {{-- Show this button only on Firefox as it's a clickable overlay that sits over the top of the default date input calendar icon to display our date picker... --}}
        <flux:button size="sm" square variant="subtle" class="not-[@supports(-moz-appearance:none)]:hidden absolute! w-6! h-auto! right-3.5 top-2 bottom-2 sm:w-6! sm:right-3 sm:top-2 sm:bottom-2" />
    </x-slot>
</flux:input>
