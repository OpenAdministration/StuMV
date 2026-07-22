<x-auth-card>
    <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-800 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-xs">
        <x-auth-logo :branding="$branding" />
        <flux:heading size="xl">{{ __('identity_providers.complete_registration_title') }}</flux:heading>
        <flux:text>{{ __('identity_providers.complete_registration_explanation', ['email' => $email]) }}</flux:text>
        <x-livewire-form>
            <div class="flex flex-col gap-4">
                <flux:field>
                    <flux:label>{{ __('common.username') }}</flux:label>
                    <flux:input wire:model.live="username" type="text" autofocus />
                    <flux:error name="username" class="mb-2" />
                    <flux:description>{{ __('validation.username', ['attribute' => __('common.username')]) }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('user.first_name_label') }}</flux:label>
                    <flux:input wire:model.live="first_name" type="text" />
                    <flux:error name="first_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('user.last_name_label') }}</flux:label>
                    <flux:input wire:model.live="last_name" type="text" />
                    <flux:error name="last_name" />
                </flux:field>
            </div>

            <x-slot:abort_route>{{ route('realm.login', ['realm' => $realm_uid]) }}</x-slot:abort_route>
            <x-slot:submit_label>{{ __('identity_providers.complete_registration_submit') }}</x-slot:submit_label>
            <x-slot:submit_icon>user-plus</x-slot:submit_icon>
        </x-livewire-form>
    </flux:card>
</x-auth-card>
