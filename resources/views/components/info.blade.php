<flux:modal.trigger name="info">
    <flux:navmenu.item icon="info">
        {{ __('common.about') }} {{ config('app.name') }} &hellip;
    </flux:navmenu.item>
</flux:modal.trigger>

<flux:modal name="info" class="md:w-[30rem] mx-auto">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" class="modal-header">{{ config('app.name') }}</flux:heading>
            <div class="text-center">
                <h3 class="text-xl font-bold text-zinc-800 dark:text-white">{{ config('app.name') }} <span class="font-normal ml-1"> 2.0.0</span></h3>
                @if(config('app.name') === 'StuMV')
                    <div class="mt-6">
                        <p><b>Stu</b>dentische <b>M</b>itglieder-<b>V</b>erwaltung</p>
                    </div>
                @endif
                <div class="mt-6">
                    <p>&copy; 2020 &ndash; {{ date("Y") }} Open Administration GmbH</p>
                    <p class="mt-2"><span class="font-semibold">{{ __('common.footer_license') }}:</span> <flux:link href="https://www.gnu.org/licenses/agpl-3.0.txt" target="_blank" rel="noopener noreferrer">AGPLv3</flux:link></p>
                </div>
                <div class="mt-6">
                    @if(config('app.name') !== 'StuMV')
                        <p class="text-zinc-800 dark:text-white mb-2">{{ __('common.based_on', ['name' => config('app.name')]) }} <flux:link href="https://github.com/OpenAdministration/StuMV" target="_blank" rel="noopener noreferrer">StuMV</flux:link>.</p>
                    @endif
                    <div class="text-zinc-800 dark:text-white flex space-x-1 items-center justify-center">
                        <span>{{ __('common.footer_code_with') }}</span>
                        <flux:icon name="heart" class="size-4 text-red-600" />
                        <span>{{ __('common.footer_by') }}</span>
                        <flux:link href="https://open-administration.de" target="_blank" rel="noopener noreferrer">Open Administration</flux:link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</flux:modal>