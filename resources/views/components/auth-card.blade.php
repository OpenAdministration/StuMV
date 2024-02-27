<div class="flex flex-col sm:justify-center items-center pt-6 sm:pt-0 space-y-3">
    <div class="flex flex-col items-center w-full space-y-1">
        <div class="flex items-center space-x-3">
            @empty($logo)
                <x-application-logo class="w-20 h-20"/>

            @else
                {{ $logo }}
            @endempty
                <span class="text-5xl font-extrabold text-gray-800 tracking-tighter">{{ config('app.name') }}</span>
        </div>
        @if(config('app.name') === "StuMV")
            <span class="text-sm font-medium text-gray-500 tracking-[.3em]">
            <span class="font-bold text-gray-600">Stu</span>dentische
            <span class="font-bold text-gray-600">M</span>itglieder
            <span class="font-bold text-gray-600">V</span>erwaltung
        </span>
        @endif
    </div>

    <div {{ $slot->attributes->merge(["class" => "w-full sm:max-w-md p-6 bg-white shadow-md overflow-hidden sm:rounded-lg"]) }}>
        {{ $slot }}
    </div>
    @isset($footer->attributes)
        <div {{ $footer->attributes }}>
            {{ $footer ?? '' }}
        </div>
    @endif
</div>

