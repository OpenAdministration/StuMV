<div>
    <x-livewire-form class="max-w-6xl mx-auto w-full space-y-8">
        <div>
            <flux:heading size="xl">{{ __('roles.new_button') }}</flux:heading>
        </div>

        <flux:field>
            <flux:label>{{ __('roles.short_name_label') }}</flux:label>
            <flux:description>{{ __('roles.new_hint_shortname') }}</flux:description>
            <flux:input wire:model.live="cn" />
            <flux:error name="cn" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('roles.full_name_label') }}</flux:label>
            <flux:description>{{ __('roles.new_hint_longname') }}</flux:description>
            <flux:input wire:model.live="description" />
            <flux:error name="description" />
        </flux:field>

        <x-slot:abort_route>
            {{ route('committees.roles', ['realm' => $uid, 'ou' => $ou]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
