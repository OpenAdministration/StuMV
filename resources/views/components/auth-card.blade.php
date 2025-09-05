<div class="flex flex-col sm:justify-center items-center p-6 sm:pt-0 space-y-8">
    <div class="flex flex-col items-center w-full space-y-1">
        <div class="flex items-center space-x-6">
            @empty($logo)
                <x-application-logo class="w-20 h-20"/>
            @else
                {{ $logo }}
            @endempty
            <div class="flex flex-col gap-y-1">
                @if(config('app.name') === "StuMV")
                    <span class="text-4xl font-bold text-zinc-800">{{ config('app.name') }}</span>
                    <span class="text-zinc-600">
                        <span class="font-bold text-zinc-700">Stu</span>dentische <span class="font-bold text-zinc-700">M</span>itglieder-<span class="font-bold text-zinc-700">V</span>erwaltung
                    </span>
                @else
                    <span class="text-4xl font-bold text-zinc-800">{{ config('app.name') }}</span>
                @endif
            </div>
        </div>
    </div>

    <flux:card class="space-y-6 max-w-4xl!">
        {{ $slot }}
    </flux:card>
    @isset($footer->attributes)
        <div {{ $footer->attributes }}>
            {{ $footer ?? '' }}
        </div>
    @endif
</div>

