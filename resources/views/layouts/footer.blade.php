<footer class="grid lg:grid-cols-2 items-center lg:justify-between px-6 py-4 gap-6 mt-auto text-sm border-t border-zinc-200 dark:border-zinc-800">
    <div class="flex justify-center lg:justify-start">
        <span class="flex space-x-1 items-center justify-center text-baser">
            @if (Config::get('app.name') == 'StuMV')
                <span>{{ __('common.footer_code_with') }}</span>
                <flux:icon name="heart" class="size-4 text-red-600" />
                <span>{{ __('common.footer_by') }}</span>
                <flux:link href="https://open-administration.de" target="_blank" rel="noopener noreferrer">Open Administration</flux:link>
            @else
            <span>{{ __('common.based_on', ['name' => Config::get('app.name')]) }} <flux:link href="https://www.stufis.de/stumv" target="_blank" rel="noopener noreferrer">StuMV</flux:link>.</span>
            @endif
        </span>
    </div>
    <div class="flex justify-center lg:justify-end">
        <span class="flex gap-2">
            @if (Config::get('app.about_url') != '')
            <flux:button size="sm" target="_blank" icon="external-link" :href="route('about')">{{ __('common.footer_about') }}</flux:button>
            @endif
            @if (Config::get('app.terms_url') != '')
            <flux:button size="sm" target="_blank" icon="external-link" :href="route('terms')">{{ __('common.footer_terms') }}</flux:button>
            @endif
            @if (Config::get('app.privacy_url') != '')
            <flux:button size="sm" target="_blank" icon="external-link" :href="route('privacy')">{{ __('common.footer_privacy') }}</flux:button>
            @endif
        </span>
    </div>
</footer>
