<div class="max-w-6xl mx-auto w-full">
    <div class="grid md:grid-cols-2 gap-6">
        <div class="space-y-4">
            <flux:textarea
                label="{{ __('tools.emailAddresses') }}"
                wire:model.blur="emailAddressesInput"
            />
            <flux:button
                variant="primary"
                wire:click="compareEmailAddressesWithLdap"
            >
                {{ __('tools.checkForMatches') }}
            </flux:button>
        </div>
        <div>
            <flux:fieldset>
                <flux:legend>{{ __('tools.matches') }}</flux:legend>
                <div class="-mt-3 flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
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
