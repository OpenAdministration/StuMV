<x-profile-layout :realm="$realm_uid" :username="$currentUsername" :name="$givenName.' '.$sn">
    <div class="max-w-7xl mx-auto">
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label class="block">{{ __('common.password') }}</flux:label>
                <flux:description class="block">{{ __('user.help.password') }}</flux:description>
                <flux:input type="password" wire:model.live="password" />
                <flux:error name="password" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('profile.password_confirm_label') }}</flux:label>
                <flux:input type="password" wire:model.live="password_confirmation" />
                <flux:error name="password_confirmation" />
            </flux:field>

            <div class="flex items-center justify-end gap-x-3">
                <flux:button variant="primary" icon="save" type="submit">{{ __('common.save') }}</flux:button>
            </div>
        </form>
    </div>
</x-profile-layout>
