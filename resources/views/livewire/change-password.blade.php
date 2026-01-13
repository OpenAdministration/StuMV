<div>
    <x-navbar-profile :username="$currentUsername" />

    <div class="mt-6 space-y-8">
        <x-livewire-form>
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
