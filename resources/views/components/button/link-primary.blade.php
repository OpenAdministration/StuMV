<button wire:navigate role="link"
    {{ $attributes->merge([
        'class' => "inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm
        hover:bg-indigo-500 disabled:hover:bg-indigo-600
        focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
        disabled:opacity-25 disabled:cursor-not-allowed",
    ]) }}
>
    @if($attributes->has('icon-leading'))
        <x-dynamic-component class="w-4 h-4" :component="$attributes->get('icon-leading')"/>
    @endif
    {{ $slot }}
    @if($attributes->has('icon-trailing'))
        <x-dynamic-component :component="$attributes->get('icon-trailing')" class=""/>
    @endif
</button>
