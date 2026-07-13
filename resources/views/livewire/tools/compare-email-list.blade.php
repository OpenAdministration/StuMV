<div class="max-w-6xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.compareEmailList_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('tools.compareEmailList_explanation') }}</flux:text>
    </div>
    <div class="grid md:grid-cols-2 gap-6 pb-6 sm:pb-8">
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
            @if($comparisonCompleted)
                <flux:fieldset>
                    <flux:legend>{{ __('tools.matches') }}</flux:legend>
                    @if($noMatches)
                        <flux:callout
                            variant="warning"
                            icon="circle-alert"
                            heading="{{ __('tools.noMatchesFound') }}"
                            class="mt-[.35rem]"
                        />
                    @else
                        <div class="space-y-4 mt-4">
                            @foreach($matches as $user)
                                <flux:card>
                                    <div class="flex flex-col">
                                        <flux:link
                                            wire:navigate
                                            href="{{ route('profile', ['username' => $user['uid']]) }}"
                                        >
                                            {{ $user['cn'] }}
                                        </flux:link>
                                        <flux:text class="text-sm">{{ $user['email'] }}</flux:text>
                                    </div>
                                </flux:card>
                            @endforeach
                        </div>
                    @endif
                </flux:fieldset>
            @endif
        </div>
    </div>
</div>
