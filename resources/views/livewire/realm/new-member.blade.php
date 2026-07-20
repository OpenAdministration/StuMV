<div>
    <x-livewire-form class="space-y-8">
        <div class="space-y-4">
            <flux:heading size="xl">{{ __('realms.members_new_heading') }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.members_new_explanation') }}</flux:text>
        </div>

        <div class="flex flex-col gap-6">
            <flux:field>
                <flux:label>{{ __('common.email') }}</flux:label>
                <flux:input wire:model="email" type="email" autofocus />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('common.username') }}</flux:label>
                <flux:input wire:model="username" type="text" />
                <flux:error name="username" class="mb-2" />
                <flux:description>{{ __('validation.username', ['attribute' => __('common.username')]) }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('user.first_name_label') }}</flux:label>
                <flux:input wire:model="first_name" type="text" />
                <flux:error name="first_name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('user.last_name_label') }}</flux:label>
                <flux:input wire:model="last_name" type="text" />
                <flux:error name="last_name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('common.password') }}</flux:label>
                <flux:input wire:model="password" type="password" />
                <flux:error name="password" class="mb-2" />
                <flux:description>{{ __('user.help.password') }}</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('common.confirm_password') }}</flux:label>
                <flux:input wire:model="password_confirmation" type="password" />
                <flux:error name="password_confirmation" />
            </flux:field>
        </div>

        <x-slot:abort_route>
            {{ route('realms.members', ['realm' => $realm_uid]) }}
        </x-slot:abort_route>
        <x-slot:submit_icon>user-plus</x-slot:submit_icon>
    </x-livewire-form>
</div>
