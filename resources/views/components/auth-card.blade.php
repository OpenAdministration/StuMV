<div class="flex flex-col items-center space-y-8">
    <div class="flex flex-col items-center w-full space-y-1 mt-2 mb-12">
        <div class="flex items-center space-x-6">
            @empty($logo)
                <x-application-logo class="w-20 h-20"/>
            @else
                {{ $logo }}
            @endempty
            <div class="flex flex-col gap-y-1">
                @if(config('app.name') === "StuMV")
                    <span class="text-4xl font-bold text-zinc-800 dark:text-white">{{ config('app.name') }}</span>
                    <span class="text-zinc-800 dark:text-white hidden sm:inline!">
                        <span class="font-bold">Stu</span>dentische <span class="font-bold">M</span>itglieder-<span class="font-bold">V</span>erwaltung
                    </span>
                @else
                    <span class="text-4xl font-bold text-zinc-800 dark:text-white">{{ config('app.name') }}</span>
                @endif
            </div>
        </div>
    </div>

    {{ $slot }}

    @isset($footer->attributes)
        <div {{ $footer->attributes }}>
            {{ $footer ?? '' }}
        </div>
    @endif
</div>

