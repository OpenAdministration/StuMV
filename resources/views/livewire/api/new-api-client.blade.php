@if($createdClientSecret)
    <div class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('api_clients.new_title') }}</flux:heading>
            <flux:callout variant="success" icon="circle-check" heading="{{ __('api_clients.created_success') }}" />
        </div>

        <flux:field>
            <flux:label>{{ __('api_clients.client_id') }}</flux:label>
            <flux:input readonly copyable value="{{ $createdClientId }}" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('api_clients.client_secret') }}</flux:label>
            <flux:input readonly copyable value="{{ $createdClientSecret }}" />
        </flux:field>

        <flux:callout variant="warning" icon="triangle-alert" heading="{{ __('api_clients.client_secret_warning') }}" />

        <div class="flex justify-end">
            <flux:button variant="primary" wire:navigate href="{{ route('realms.api-clients', ['realm' => $uid]) }}">
                {{ __('api_clients.done') }}
            </flux:button>
        </div>
    </div>
@else
    <div>
        <x-livewire-form class="space-y-8">
            <div>
                <flux:heading size="xl" class="mb-4">{{ __('api_clients.new_title') }}</flux:heading>
                <flux:text class="text-base">{{ __('api_clients.explanation') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('api_clients.name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('api_clients.name_placeholder') }}" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('api_clients.scopes') }}</flux:label>
                <flux:pillbox multiple wire:model="scopes">
                    <flux:pillbox.option value="committees">{{ __('api_clients.scope_committees') }}</flux:pillbox.option>
                    <flux:pillbox.option value="groups">{{ __('api_clients.scope_groups') }}</flux:pillbox.option>
                    <flux:pillbox.option value="users">{{ __('api_clients.scope_users') }}</flux:pillbox.option>
                </flux:pillbox>
            </flux:field>

            <x-slot:abort_route>
                {{ route('realms.api-clients', ['realm' => $uid]) }}
            </x-slot:abort_route>
        </x-livewire-form>
    </div>
@endif
