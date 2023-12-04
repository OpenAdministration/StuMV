<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'flex gap-x-1.5 items-center text-red-500 hover:text-red-700' . ($attributes->get('disabled') ? ' opacity-75 cursor-not-allowed' : ''),
    ]) }}
>
    @if($attributes->has('icon-leading'))
        <x-dynamic-component class="w-4 h-4" :component="$attributes->get('icon-leading')"/>
    @endif
    <span>{{ $slot }}</span>
    @if($attributes->has('icon-trailing'))
        <x-dynamic-component :component="$attributes->get('icon-trailing')" class=""/>
    @endif
</button>
