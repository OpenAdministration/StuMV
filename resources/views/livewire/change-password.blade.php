<div>
    <x-navbar-profile :username="$currentUsername" />

    <div class="mt-6">
        <x-livewire-form class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Password') }}</flux:label>
                <flux:description>{{ __('user.help.password') }}</flux:description>
                <flux:input type="password" wire:model="password" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Password confirm') }}</flux:label>
                <flux:input type="password" wire:model="password_confirmation" />
            </flux:field>

            <x-slot:abort_route>{{ back() }}</x-slot:abort_route>
        </x-livewire-form>
    </div>
</div>
