<div class="flex-col space-y-8">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <flux:heading size="xl" class="mb-4">{{ __('groups.roles_add_headline', ['name' => $group_cn]) }}</flux:heading>
            <flux:text class="text-base">{{  __('groups.roles_add_explanation', ['name' => $group_cn]) }}</flux:text>
        </div>
    </div>
    <x-livewire-form class="space-y-8">
        <flux:field>
            <flux:label>{{ __('Committee') }}</flux:label>
            <flux:select
                variant="listbox"
                searchable
                wire:model.live="selected_committee_dn"
            >
                @foreach($committees as $committee)
                    <flux:select.option value="{{ $committee->getDn() }}">{{ $committee->getFirstAttribute('description') }} ({{ $committee->getFirstAttribute('ou') }})</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Role') }}</flux:label>
            <flux:select
                variant="listbox"
                searchable
                wire:model="selected_role_dn"
                :disabled="empty($selected_committee_dn)"
            >
                @foreach($roles as $role)
                    <flux:select.option value="{{ $role->getDn() }}">{{ $role->getFirstAttribute('description') }} ({{ $role->getFirstAttribute('cn') }})</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    </x-livewire-form>
</div>
