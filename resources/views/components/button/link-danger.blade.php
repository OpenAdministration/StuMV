<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'flex gap-x-1.5 items-center text-red-500 enabled:hover:text-red-600 disabled:opacity-25 disabled:cursor-not-allowed',
    ]) }}
>
    @if($attributes->has('icon-leading'))
        <x-dynamic-component class="w-4 h-4" :component="$attributes->get('icon-leading')"/>
    @endif
    <span>{{ $slot }}</span>
    @if($attributes->has('icon-trailing'))
        <x-dynamic-component :component="$attributes->get('icon-trailing')" class="w-4 h-4"/>
    @endif
</button>
