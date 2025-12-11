<footer class="grid lg:grid-cols-2 items-center lg:justify-between px-6 py-4 gap-6 mt-auto text-sm border-t border-zinc-200 dark:border-zinc-800">
    <div class="flex justify-center lg:justify-start">
        <span class="flex space-x-1 items-center justify-center text-baser">
            @if (Config::get('app.name') == 'StuMV')
                <span>{{ __('code with') }}</span>
                <flux:icon name="heart" class="text-red-600" />
                <span>{{ __('by') }}</span>
                <flux:link href="https://open-administration.de" target="_blank" rel="noopener noreferrer">Open Administration</flux:link>
            @else
            <span>{{ Config::get('app.name') }} is based on <flux:link href="https://www.stufis.de/stumv" target="_blank" rel="noopener noreferrer">StuMV</flux:link>.</span>
            @endif
        </span>
    </div>
    <div class="flex justify-center lg:justify-end">
        <span class="flex gap-2">
            @if (Config::get('app.about_url') != '')
            <flux:button size="sm" target="_blank" icon="external-link" :href="route('about')">{{ __('About') }}</flux:button>
            @endif
            @if (Config::get('app.terms_url') != '')
            <flux:button size="sm" target="_blank" icon="external-link" :href="route('terms')">{{ __('Terms') }}</flux:button>
            @endif
            @if (Config::get('app.privacy_url') != '')
            <flux:button size="sm" target="_blank" icon="external-link" :href="route('privacy')">{{ __('Privacy') }}</flux:button>
            @endif
        </span>
    </div>
</footer>
