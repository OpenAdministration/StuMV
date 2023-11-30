<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'text-indigo-600 hover:text-indigo-900' . ($attributes->get('disabled') ? ' opacity-75 cursor-not-allowed' : ''),
    ]) }}
>
    {{ $slot }}
</button>
