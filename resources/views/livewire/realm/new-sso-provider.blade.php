<div>
    <x-livewire-form class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('sso_providers.new_title') }}</flux:heading>
            <flux:text class="text-base">{{ __('sso_providers.explanation') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('sso_providers.name') }}</flux:label>
            <flux:input wire:model="name" placeholder="{{ __('sso_providers.name_placeholder') }}" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('sso_providers.issuer') }}</flux:label>
            <flux:description>{{ __('sso_providers.issuer_description') }}</flux:description>
            <flux:input wire:model="issuer" placeholder="https://idp.example.com" />
            <flux:error name="issuer" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('sso_providers.client_id') }}</flux:label>
            <flux:input wire:model="client_id" />
            <flux:error name="client_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('sso_providers.client_secret') }}</flux:label>
            <flux:input type="password" wire:model="client_secret" />
            <flux:error name="client_secret" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('sso_providers.groups_claim') }}</flux:label>
            <flux:description>{{ __('sso_providers.groups_claim_description') }}</flux:description>
            <flux:input wire:model="groups_claim" />
            <flux:error name="groups_claim" />
        </flux:field>

        <flux:switch wire:model="enabled" label="{{ __('sso_providers.enabled') }}" />

        <x-slot:abort_route>
            {{ route('realms.sso-providers', ['realm' => $uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
