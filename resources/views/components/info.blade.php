<flux:modal.trigger name="info">
    <flux:button
        variant="ghost"
        icon="info"
        title="{{ __('common.about') }} {{ config('app.name') }} &hellip;"
    />
</flux:modal.trigger>

<flux:modal name="info" class="md:w-[30rem] mx-auto">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" class="modal-header">{{ config('app.name') }}</flux:heading>
            <div class="text-center mt-6">
                @if (config('app.name') !== 'StuMV')
                <div class="mt-2">
                    <span>{{ __('common.based_on', ['name' => config('app.name')]) }} <flux:link href="https://github.com/OpenAdministration/StuMV" target="_blank" rel="noopener noreferrer">StuMV</flux:link>.</span>
                </div>
                @endif
                <div class="mt-2 mb-6 flex space-x-1 items-center justify-center">
                    <span>{{ __('code with') }}</span>
                    <flux:icon name="heart" class="size-4 text-red-600" />
                    <span>{{ __('by') }}</span>
                    <flux:link href="https://open-administration.de" target="_blank" rel="noopener noreferrer">Open Administration</flux:link>
                </div>
                <div class="mb-6">
                    <p>&copy; 2020 &ndash; {{ date("Y") }} Open Administration GmbH</p>
                    <p class="mt-2"><span class="font-semibold">{{ __('License') }}:</span> <flux:link href="https://www.gnu.org/licenses/agpl-3.0.txt" target="_blank" rel="noopener noreferrer">AGPLv3</flux:link></p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 bg-zinc-100 dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700 -mx-6 -mb-6 p-6">
                    @if (Config::get('app.about_url') != '')
                        <flux:button size="sm" icon="external-link" target="_blank" :href="route('about')">{{ __('About') }}</flux:button>
                    @endif
                    @if (Config::get('app.terms_url') != '')
                        <flux:button size="sm" icon="external-link" target="_blank" :href="route('terms')">{{ __('Terms') }}</flux:button>
                    @endif
                    @if (Config::get('app.privacy_url') != '')
                        <flux:button size="sm" icon="external-link" target="_blank" :href="route('privacy')">{{ __('Privacy') }}</flux:button>
                    @endif
                    <flux:button size="sm" icon="external-link" target="_blank" rel="noopener noreferrer" :href="route('source-code')">{{ __('Source code') }}</flux:button>
                </div>
            </div>
        </div>
    </div>
</flux:modal>