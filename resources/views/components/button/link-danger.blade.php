<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'text-red-500 hover:text-red-700' . ($attributes->get('disabled') ? ' opacity-75 cursor-not-allowed' : ''),
    ]) }}
>
    {{ $slot }}
</button>
