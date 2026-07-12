<div class="max-w-[calc(100%_+_4rem)]! w-[calc(100%_+_3rem)]! sm:w-[calc(100%_+_4rem)]! flex flex-col -m-6! sm:-m-8!">
    <div class="pt-6 sm:pt-8 px-6 sm:px-8 pb-3">
        <flux:heading size="xl" class="max-w-6xl mx-auto">{{ $givenName }} {{ $sn }}</flux:heading>
    </div>

    <x-navbar-profile :username="$currentUsername" />

    <div class="flex-1 p-6 sm:p-8 overflow-y-auto">
        <div class="max-w-6xl mx-auto space-y-6">
            <x-livewire-form class="space-y-6">
                <flux:field>
                    <flux:label class="block">{{ __('Password') }}</flux:label>
                    <flux:description class="block">{{ __('user.help.password') }}</flux:description>
                    <flux:input type="password" wire:model="password" />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Password confirm') }}</flux:label>
                    <flux:input type="password" wire:model="password_confirmation" />
                    <flux:error name="password_confirmation" />
                </flux:field>
            </x-livewire-form>
        </div>
    </div>
</div>
