@unless($attributes->get('disabled'))
    <a {{ $attributes->merge([
        'class' => 'flex gap-x-1.5 items-center text-indigo-600 hover:text-indigo-900',
    ]) }}>
        {{ $slot }}
    </a>
@else
    <span {{ $attributes->except(['wire:click', 'href'])->merge([
        'class' => 'opacity-25 cursor-not-allowed flex gap-x-1.5 items-center text-indigo-600'
    ])}}>
        {{ $slot }}
    </span>
@endunless
