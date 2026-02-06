<div class="max-w-6xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.usersNotInUniLdap_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('tools.usersNotInUniLdap_explanation') }}</flux:text>
    </div>
    <div class="pb-6 sm:pb-8 space-y-6">
        <div>
            @if($unildapDataExists)
                @if(!$comparisonCompleted)
                    <flux:button
                        variant="primary"
                        icon="search"
                        wire:click="searchForUsersNotInUniLdap"
                    >
                        {{ __('tools.startSearch') }}
                    </flux:button>
                @endif
            @else
                <flux:callout
                    variant="danger"
                    icon="circle-x"
                    heading="{{ __('tools.setUniLdapDataFirst') }}"
                    class="mt-[.35rem]"
                />
            @endif
        </div>
        <div>
            @if($comparisonCompleted && count($results) > 0)
                <flux:fieldset>
                    <flux:legend class="w-full flex py-3 border-b border-zinc-800/10 dark:border-white/20 font-bold">
                        {{ __('tools.matches') }} <flux:badge class="ml-auto">{{ count($results) }}</flux:badge>
                    </flux:legend>
                    <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($results as $user)
                            <div class="flex items-center py-3">
                                <div class="flex-1">
                                    <flux:link
                                        wire:navigate
                                        href="{{ route('profile', ['username' => $user['uid']]) }}"
                                    >
                                        {{ $user['cn'] }}
                                    </flux:link>
                                </div>
                                <div>
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="trash-2"
                                        wire:click="confirmDeleteUser('{{ $user['uid'] }}')"
                                    >
                                        {{ __('tools.delete') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:fieldset>
            @elseif($comparisonCompleted && count($results) < 1)
                <flux:callout
                    variant="danger"
                    icon="circle-x"
                    heading="{{ __('tools.noMatchesFound') }}"
                    class="mt-[.35rem]"
                />
            @endif
        </div>
    </div>
    <flux:modal name="confirm-delete-user" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('tools.deleteUser') }}</flux:heading>
                <flux:text class="mt-2">{{ __('tools.deleteUserText') }}</flux:text>
            </div>
            <div class="flex justify-end">
                <flux:button icon="ban" x-on:click="$flux.modal('confirm-delete-user').close()">{{ __('tools.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="deleteUser">{{ __('tools.deleteUser') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
