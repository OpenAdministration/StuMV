<x-auth-card>
    <flux:card class="p-0 w-full bg-white dark:bg-zinc-800 max-w-[28rem]! mx-auto border-1 shadow-sm divide-y divide-zinc-200 dark:divide-zinc-700">
        <div class="p-6">
            <x-auth-logo :branding="$branding" />
        </div>

        <x-livewire-form class="divide-y divide-zinc-200 dark:divide-zinc-700 [&>div]:px-6 [&>div]:pb-6">
            <div class="flex flex-col gap-4 pt-6">
                <flux:heading size="xl">{{ __('identity_providers.complete_registration_title') }}</flux:heading>
                <flux:text>{{ __('identity_providers.complete_registration_explanation', ['email' => $email]) }}</flux:text>

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
