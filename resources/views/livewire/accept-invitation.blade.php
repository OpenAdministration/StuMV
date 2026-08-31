<x-auth-card>
    <flux:card class="p-0 w-full bg-zinc-50 dark:bg-zinc-800 max-w-[28rem]! mx-auto border-1 shadow-sm divide-y divide-zinc-200 dark:divide-zinc-700">
        <div class="p-6">
            <x-auth-logo :branding="$branding" />
        </div>

        <x-livewire-form class="divide-y divide-zinc-200 dark:divide-zinc-700 [&>div]:px-6 [&>div]:pb-6">
            <div class="flex flex-col gap-4 pt-6">
                <flux:heading size="xl">{{ __('invitations.accept_title') }}</flux:heading>
                <flux:text>{{ __('invitations.accept_explanation') }}</flux:text>

                <flux:field>
                    <flux:label>{{ __('common.email') }}</flux:label>
                    <flux:input value="{{ $email }}" type="email" disabled />
                </flux:field>

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

                <flux:field>
                    <flux:label>{{ __('common.password') }}</flux:label>
                    <flux:input wire:model.live="password" type="password" />
                    <flux:error name="password" class="mb-2" />
                    <flux:description>{{ __('user.help.password') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('common.confirm_password') }}</flux:label>
                    <flux:input wire:model.live="password_confirmation" type="password" />
                    <flux:error name="password_confirmation" />
                </flux:field>
            </div>

            <x-slot:abort_route>{{ route('realm.login', ['realm' => $realm_uid]) }}</x-slot:abort_route>
            <x-slot:submit_label>{{ __('invitations.accept_button') }}</x-slot:submit_label>
            <x-slot:submit_icon>user-plus</x-slot:submit_icon>
        </x-livewire-form>
    </flux:card>
</x-auth-card>
