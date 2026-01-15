<x-auth-card>
    <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-900 sm:bg-white sm:dark-bg-zinc-800 sm:max-w-[25rem]! md:max-w-2xl! lg:max-w-4xl! mx-auto border-0 sm:border-1 sm:shadow-xs">
        <flux:heading size="xl">{{ __('user.register') }}</flux:heading>
        <x-livewire-form>
            <div class="flex flex-col gap-6">
                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model.blur="email" type="email" autofocus />
                    <flux:description>{{ __('user.help.only_uni_mail') }}</flux:descripton>
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Username') }}</flux:label>
                    <flux:input wire:model.blur="username" type="text" />
                    <flux:description>{{ __('validation.username', ['attribute' => __('Username')]) }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('First name') }}</flux:label>
                    <flux:input wire:model.blur="first_name" type="text" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Last name') }}</flux:label>
                    <flux:input wire:model.blur="last_name" type="text" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Password') }}</flux:label>
                    <flux:input wire:model.blur="password" type="password" />
                    <flux:description>{{ __('user.help.password') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Confirm Password') }}</flux:label>
                    <flux:input wire:model.blur="password_confirmation" type="password" />
                    <flux:description></flux:description>
                </flux:field>
            </div>

            <x-slot:abort_route>{{ route('login') }}</x-slot:abort_route>
        </x-livewire-form>
    </flux:card>
</x-auth-card>
