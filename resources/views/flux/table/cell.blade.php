@pure

@props([
    'align' => 'start',
    'variant' => null,
])

@php
$classes = Flux::classes()
    ->add('py-3 px-3 first:ps-0 last:pe-0 text-sm')
    ->add(match($align) {
        'center' => 'text-center',
        'end' => 'text-end',
        default => '',
    })
    ->add(match ($variant) {
        'strong' => 'font-medium text-zinc-800 dark:text-white',
        default => 'text-zinc-500 dark:text-zinc-300',
    })
    ;
@endphp

<td {{ $attributes->class($classes) }} data-flux-cell>
    {{ $slot }}
</td>
