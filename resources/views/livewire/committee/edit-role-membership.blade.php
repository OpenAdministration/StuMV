<x-livewire-form class="max-w-6xl mx-auto w-full">
    <div class="mb-6">
        <flux:heading size="xl" class="mb-4">{{  __('roles.membership_edit_headline', ['name' => $cn]) }}</flux:heading>
        <flux:text class="text-base">
            {{  __('roles.membership_edit_explanation', ['name' => $cn]) }}
        </flux:text>
    </div>
    <div class="grid sm:grid-cols-2 gap-6 mb-6">
        <flux:field class="col-span-full">
            <flux:label>{{ __('roles.short_name_label') }}</flux:label>
            <flux:input type="text" wire:model="cn" disabled />
        </flux:field>
        <flux:field class="col-span-full">
            <flux:label>{{ __('common.username') }}</flux:label>
            <flux:input wire:model="username" disabled />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('roles.membership_starting') }}</flux:label>
            <flux:input type="date" wire:model="start_date" />
            <flux:error name="start_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('roles.membership_ending') }}</flux:label>
            <flux:input type="date" wire:model="end_date" />
            <flux:error name="end_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('roles.membership_decided') }}</flux:label>
            <flux:input type="date" wire:model="decision_date" />
            <flux:error name="decision_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('roles.membership_comment') }}</flux:label>
            <flux:input wire:model="comment" />
            <flux:error name="comment" />
        </flux:field>
    </div>
</x-livewire-form>
