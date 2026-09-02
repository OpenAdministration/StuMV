<div>
    <x-livewire-form class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('identity_providers.new_title') }}</flux:heading>
            <flux:text class="text-base">{{ __('identity_providers.explanation') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('identity_providers.name') }}</flux:label>
            <flux:input wire:model="name" placeholder="{{ __('identity_providers.name_placeholder') }}" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('identity_providers.issuer') }}</flux:label>
            <flux:description>{{ __('identity_providers.issuer_description') }}</flux:description>
            <flux:input wire:model="issuer" placeholder="https://idp.example.com" />
            <flux:error name="issuer" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('identity_providers.client_id') }}</flux:label>
            <flux:input wire:model="client_id" />
            <flux:error name="client_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('identity_providers.client_secret') }}</flux:label>
            <flux:input type="password" wire:model="client_secret" />
            <flux:error name="client_secret" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('identity_providers.scopes') }}</flux:label>
            <flux:description>{{ __('identity_providers.scopes_description') }}</flux:description>
            <flux:input wire:model="scopes" />
            <flux:error name="scopes" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('identity_providers.groups_claim') }}</flux:label>
            <flux:description>{{ __('identity_providers.groups_claim_description') }}</flux:description>
            <flux:input wire:model="groups_claim" />
            <flux:error name="groups_claim" />
        </flux:field>

        <flux:switch wire:model="enabled" label="{{ __('identity_providers.enabled') }}" />

        <x-slot:abort_route>
            {{ route('realms.identity-providers', ['realm' => $uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
