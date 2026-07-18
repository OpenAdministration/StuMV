<x-auth-card>
    <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-900 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-xs">
        <flux:heading size="xl">{{ __('user.register') }}</flux:heading>
        <x-livewire-form>
            <div class="flex flex-col gap-6">
                <flux:field>
                    <flux:label>{{ __('common.email') }}</flux:label>
                    <flux:input wire:model.live="email" type="email" autofocus />
                    <flux:error name="email" class="mb-2" />
                    <flux:error name="domain" class="mb-2" />
                    <flux:description>{{ __('user.help.only_uni_mail') }}</flux:descripton>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('common.username') }}</flux:label>
                    <flux:input wire:model.live="username" type="text" />
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

            <x-slot:abort_route>{{ route('login') }}</x-slot:abort_route>
            <x-slot:submit_label>{{ __('user.register') }}</x-slot:submit_label>
            <x-slot:submit_icon>user-plus</x-slot:submit_icon>
        </x-livewire-form>
    </flux:card>
</x-auth-card>
