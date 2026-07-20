<div class="flex flex-col items-center space-y-8 m-auto!">
    {{ $slot }}

    @isset($footer->attributes)
        <div {{ $footer->attributes }}>
            {{ $footer ?? '' }}
        </div>
    @endif
</div>

