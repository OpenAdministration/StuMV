@if(($branding ?? null)?->background_id)
    <footer class="grid lg:grid-cols-2 items-center lg:justify-between px-6 py-4 gap-6 mt-auto text-sm border-t bg-zinc-50/90 dark:bg-zinc-900/90 border-black/20 dark:border-white/20 shadow-sm">
@else
    <footer class="grid lg:grid-cols-2 items-center lg:justify-between px-6 py-4 gap-6 mt-auto text-sm border-t bg-zinc-50 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
@endif
    <div class="flex justify-center lg:justify-start">
        <span class="flex space-x-1 items-center justify-center">
            @if (Config::get('app.name') == 'StuMV')
                <span>{{ __('common.footer_code_with') }}</span>
                <flux:icon name="heart" class="size-4 text-red-600" />
                <span>{{ __('common.footer_by') }}</span>
                <flux:link
                    href="https://open-administration.de"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-zinc-800 dark:text-white"
                >
                    Open Administration GmbH
                </flux:link>
            @else
            <span>
                {{ __('common.based_on', ['name' => Config::get('app.name')]) }}
                <flux:link
                    href="https://www.stufis.de/stumv"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-zinc-800 dark:text-white"
                >
                    StuMV
                </flux:link>
                .
            </span>
            @endif
        </span>
    </div>
    <div class="flex justify-center lg:justify-end">
        <span class="flex flex-wrap justify-center lg:justify-end gap-2">
            @if(config('app.imprint_url') !== '')
                <flux:button size="sm" target="_blank" icon="external-link" :href="route('imprint')">{{ __('common.footer_imprint') }}</flux:button>
            @endif
            @if(config('app.terms_url') !== '')
                <flux:button size="sm" target="_blank" icon="external-link" :href="route('terms')">{{ __('common.footer_terms') }}</flux:button>
            @endif
            @if(config('app.privacy_url') !== '')
                <flux:button size="sm" target="_blank" icon="external-link" :href="route('privacy')">{{ __('common.footer_privacy') }}</flux:button>
            @endif
        </span>
    </div>
</footer>
