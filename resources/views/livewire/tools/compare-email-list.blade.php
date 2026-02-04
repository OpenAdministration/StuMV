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

                @foreach($matches as $user)
                    <div class="w-full px-4 py-2 border-b border-zinc-200 dark:border-zinc-700">
                        <flux:link
                            wire:navigate
                            href="{{ route('profile', ['username' => $user['uid']]) }}"
                        >
                            {{ $user['cn'] }}
                        </flux:link>
                    </div>
                @endforeach
            </flux:fieldset>
        </div>
    </div>
</div>
