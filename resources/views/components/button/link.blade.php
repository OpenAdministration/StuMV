<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'text-indigo-600 enabled:hover:text-indigo-900 disabled:opacity-25 disabled:cursor-not-allowed',
    ]) }}
>
    {{ $slot }}
</button>
