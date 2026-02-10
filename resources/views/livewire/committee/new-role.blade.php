<x-livewire-form class="max-w-6xl mx-auto w-full space-y-8">
    <div>
        <flux:heading size="xl">{{ __('New Role') }}</flux:heading>
    </div>

    <flux:field>
        <flux:label>{{ __('Short Rolename') }}</flux:label>
        <flux:description>{{ __('roles.new_hint_shortname') }}</flux:description>
        <flux:input wire:model.live="cn" />
        <flux:error name="cn" />
    </flux:field>
    
    <flux:field>
        <flux:label>{{ __('Full Rolename') }}</flux:label>
        <flux:description>{{ __('roles.new_hint_longname') }}</flux:description>
        <flux:input wire:model.live="description" />
        <flux:error name="description" />
    </flux:field>

    <x-slot:abort_route>
        {{ route('committees.roles', ['uid' => $uid, 'ou' => $ou]) }}
    </x-slot:abort_route>
</x-livewire-form>
