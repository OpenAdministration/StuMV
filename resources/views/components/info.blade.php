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
            <div class="text-center">
                <h3 class="text-xl font-bold text-zinc-800 dark:text-white">{{ config('app.name') }} <span class="font-normal ml-1"> 2.0.0</span></h3>
                <div class="mt-6">
                    <p>&copy; 2020 &ndash; {{ date("Y") }} Open Administration GmbH</p>
                    <p class="mt-2"><span class="font-semibold">{{ __('common.footer_license') }}:</span> <flux:link href="https://www.gnu.org/licenses/agpl-3.0.txt" target="_blank" rel="noopener noreferrer">AGPLv3</flux:link></p>
                </div>
                <div class="mt-6">
                    @if (config('app.name') !== 'StuMV')
                        <p class="text-zinc-800 dark:text-white mb-2">{{ __('common.based_on', ['name' => config('app.name')]) }} <flux:link href="https://github.com/OpenAdministration/StuMV" target="_blank" rel="noopener noreferrer">StuMV</flux:link>.</p>
                    @endif
                    <div class="text-zinc-800 dark:text-white flex space-x-1 items-center justify-center">
                        <span>{{ __('common.footer_code_with') }}</span>
                        <flux:icon name="heart" class="size-4 text-red-600" />
                        <span>{{ __('common.footer_by') }}</span>
                        <flux:link href="https://open-administration.de" target="_blank" rel="noopener noreferrer">Open Administration</flux:link>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 bg-zinc-100 dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700 -mx-6 -mb-6 p-6">
                    <flux:button size="sm" icon="external-link" target="_blank" rel="noopener noreferrer" :href="route('documentation')">{{ __('common.footer_documentation') }}</flux:button>
                    <flux:button size="sm" icon="external-link" target="_blank" rel="noopener noreferrer" :href="route('source-code')">{{ __('common.footer_source_code') }}</flux:button>
                </div>
            </div>
        </div>
    </div>
</flux:modal>