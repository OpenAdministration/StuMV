@aware([ 'placeholder' ])

@props([
    'placeholder' => null,
    'clearable' => null,
    'invalid' => false,
    'size' => null,
])

<flux:input :$invalid :$size :$placeholder :$attributes>
    <x-slot name="iconTrailing">
        <?php if ($clearable): ?>
            <flux:button as="div"
                class="cursor-pointer ms-2 -me-3 [[data-flux-input]:has(input:placeholder-shown)_&]:hidden [[data-flux-select]:has([disabled][data-selected])_&]:hidden"
                variant="subtle"
                :size="$size === 'sm' ? 'xs' : 'sm'"
                square
                tabindex="-1"
                aria-label="Clear selected"
                x-on:click.prevent.stop="let select = $el.closest('ui-select'); select.value = select.hasAttribute('multiple') ? [] : null; select.dispatchEvent(new Event('change', { bubbles: false })); select.dispatchEvent(new Event('input', { bubbles: false }))"
            >
                <flux:icon.x-mark variant="micro" />
            </flux:button>
        <?php endif; ?>

        <flux:button size="sm" square variant="subtle" tabindex="-1" class="-me-1 in-[[disabled]]:pointer-events-none">
            <flux:icon.chevron-up-down variant="mini" class="text-zinc-300 [[data-flux-input]:hover_&]:text-zinc-800 in-[[disabled]]:text-zinc-200! dark:text-white/60 dark:[[data-flux-input]:hover_&]:text-white dark:in-[[disabled]]:text-white/40!" />
        </flux:button>
    </x-slot>
</flux:input>
