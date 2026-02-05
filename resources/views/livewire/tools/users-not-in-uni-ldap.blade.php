<div class="max-w-6xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.usersNotInUniLdap_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('tools.usersNotInUniLdap_explanation') }}</flux:text>
    </div>
    <div class="pb-6 sm:pb-8 space-y-6">
        <div>
            @if($unildapDataExists)
                <flux:button
                    variant="primary"
                    icon="search"
                    wire:click="searchForUsersNotInUniLdap"
                >
                    {{ __('tools.startSearch') }}
                </flux:button>
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
                    <flux:legend class="py-3 border-b border-zinc-800/10 dark:border-white/20 font-bold">
                        {{ __('tools.matches') }} <flux:badge>{{ count($results) }}</flux:badge>
                    </flux:legend>
                    <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($results as $user)
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
</div>
