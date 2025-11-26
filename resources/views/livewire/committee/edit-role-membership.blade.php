<x-livewire-form>
    <div class="mb-6">
        <flux:heading size="xl" class="mb-4">{{  __('roles.membership-edit_headline', ['name' => $cn]) }}</flux:heading>
        <flux:text class="text-base">
            {{  __('roles.membership-edit_explanation', ['name' => $cn]) }}
        </flux:text>
    </div>
    <div class="grid sm:grid-cols-2 gap-6 mb-6">
        <flux:field class="col-span-full">
            <flux:label>{{ __('Short Rolename') }}</flux:label>
            <flux:input type="text" wire:model="cn" disabled />
        </flux:field>
        <flux:field class="col-span-full">
            <flux:label>{{ __('Username') }}</flux:label>
            <flux:input wire:model="username" disabled />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Starting') }}</flux:label>
            <flux:date-picker start-day="1" wire:model="start_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Ending') }}</flux:label>
            <flux:date-picker start-day="1" wire:model="end_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Decided') }}</flux:label>
            <flux:date-picker start-day="1" wire:model="decision_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Comment') }}</flux:label>
            <flux:input wire:model="comment" />
        </flux:field>
    </div>
</x-livewire-form>
