@php
    $logoID = ($branding ?? null)?->logo_id;
@endphp
<div class="flex items-center justify-center space-x-6 mb-4">
    @if($logoID)
        <img src="{{ asset('storage/realm-branding/' . $logoID) }}" alt="{{ config('app.name') }}" class="w-full h-22 object-contain">
    @else
        <x-application-logo class="w-20 h-20"/>
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
    @endif
</div>