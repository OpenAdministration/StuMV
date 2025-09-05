@pure

@php
$classes = Flux::classes()
    ->add('shrink-0 size-4.5 rounded-full')
    ->add('text-sm text-zinc-700 dark:text-zinc-800')
    ->add('shadow-xs [ui-radio[disabled]_&]:opacity-75 [ui-radio[data-checked][disabled]_&]:opacity-50 [ui-radio[disabled]_&]:shadow-none [ui-radio[data-checked]_&]:shadow-none')
    ->add('flex justify-center items-center [ui-radio[data-checked]_&>div]:block')
    ->add([
        'border',
        'border-zinc-300 dark:border-white/10',
        '[ui-radio[disabled]_&]:border-zinc-200 dark:[ui-radio[disabled]_&]:border-white/5',
        '[ui-radio[data-checked]_&]:border-transparent data-indeterminate:border-transparent',
        '[ui-radio[data-checked]_&]:[ui-radio[disabled]_&]:border-transparent data-indeterminate:border-transparent',
        '[print-color-adjust:exact]',
    ])
    ->add([
        'bg-white dark:bg-white/10',
        '[ui-radio[data-checked]_&]:bg-(--color-accent)',
        'hover:[ui-radio[data-checked]_&]:bg-(--color-accent)',
        'focus:[ui-radio[data-checked]_&]:bg-(--color-accent)',
    ])
    ;
@endphp

<div {{ $attributes->class($classes) }} data-flux-radio-indicator>
    <div class="hidden size-2 rounded-full bg-(--color-accent-foreground)"></div>
</div>
