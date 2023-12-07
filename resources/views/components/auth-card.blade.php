<div class="flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
    <div class="flex space-y-5">
        <div class="flex items-center space-x-3">
            @empty($logo)
                <x-application-logo class="w-20 h-20"/>
                <span class="text-5xl font-extrabold text-gray-800 tracking-tighter">StuMV</span>
            @else
                {{ $logo }}
            @endempty
        </div>
    </div>

    <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
        {{ $slot }}
    </div>
    @isset($footer->attributes)
        <div {{ $footer->attributes->merge(['class' => 'mt-4']) }}>
            {{ $footer ?? '' }}
        </div>
    @endif
</div>

