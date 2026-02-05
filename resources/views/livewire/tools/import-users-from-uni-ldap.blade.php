<div class="max-w-6xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.importUsersFromUniLdap_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('tools.importUsersFromUniLdap_explanation') }}</flux:text>
    </div>
    <div class="pb-6 sm:pb-8 space-y-6">
        <div class="space-y-4">
            @if($unildapDataExists)
                @if(!$searchCompleted)
                    <flux:input label="{{ __('tools.emailAddress') }}" wire:model.live.blur="email" />
                    <flux:button
                        variant="primary"
                        icon="search"
                        wire:click="getUserData"
                    >
                        {{ __('tools.getUserData') }}
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
        @if($searchCompleted && !$userNotFound)
            <div class="space-y-4">
                <flux:input label="{{ __('tools.emailAddress')" wire:model="email" />
                <flux:input label="{{ __('tools.username') }}" wire:model="username" />
                <flux:input label="{{ __('tools.firstname') }}" wire:model="firstname" />
                <flux:input label="{{ __('tools.lastname') }}" wire:model="lastname" />
                <flux:button
                    variant="primary"
                    icon="user-plus"
                    wire:click="createUser"
                >
                    {{ __('tools.createUser') }}
                </flux:button>
            </div>
        @elseif($searchCompleted && $userNotFound)
            <flux:callout
                variant="danger"
                icon="circle-x"
                heading="{{ __('tools.userNotFoundInUniLdap') }}"
                class="mt-[.35rem]"
            />
        @endif
    </div>
</div>
