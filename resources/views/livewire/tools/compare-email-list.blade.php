<div class="max-w-6xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.compareEmailList_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('tools.compareEmailList_explanation') }}</flux:text>
    </div>
    <div class="grid md:grid-cols-2 gap-6">
        <div class="space-y-4">
            <flux:textarea
                label="{{ __('tools.emailAddresses') }}"
                wire:model.blur="emailAddressesInput"
                class="h-[15rem]"
            />
            <flux:button
                variant="primary"
                icon="search"
                wire:click="compareEmailAddressesWithLdap"
            >
                {{ __('tools.checkForMatches') }}
            </flux:button>
        </div>
        <div>
            <flux:fieldset>
                <flux:legend>{{ __('tools.matches') }}</flux:legend>
                <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($matches as $user)
                        <div class="py-3">
                            <flux:link
                                wire:navigate
                                href="{{ route('profile', ['username' => $user['uid']]) }}"
                            >
                                {{ $user['cn'] }}
                            </flux:link>
                        </div>
                    @endforeach
                </div>
            </flux:fieldset>
        </div>
    </div>
</div>
