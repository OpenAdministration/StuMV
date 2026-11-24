<div class="max-w-7xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.compare_email_list_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('tools.compare_email_list_explanation') }}</flux:text>
    </div>
    <div class="grid md:grid-cols-2 gap-6">
        <div class="space-y-4">
            <flux:textarea
                label="{{ __('tools.email_addresses') }}"
                wire:model.blur="emailAddressesInput"
                class="h-[15rem]"
            />
            <flux:button
                variant="primary"
                icon="search"
                wire:click="compareEmailAddressesWithLdap"
            >
                {{ __('tools.check_for_matches') }}
            </flux:button>
        </div>
        <div>
            @if($comparisonCompleted)
                <flux:fieldset>
                    <legend>{{ __('tools.matches') }}</legend>
                    @if($noMatches)
                        <div class="p-4">
                            <flux:callout
                                variant="warning"
                                icon="circle-alert"
                                heading="{{ __('tools.no_matches_found') }}"
                            />
                        </div>
                    @else
                        <div class="space-y-4 p-4">
                            @foreach($matches as $user)
                                <flux:card class="py-4">
                                    <div class="flex flex-col">
                                        <flux:link
                                            wire:navigate
                                            href="{{ route('profile', ['realm' => $uid, 'username' => $user['uid']]) }}"
                                        >
                                            {{ $user['cn'] }}
                                        </flux:link>
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
