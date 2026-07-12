<x-livewire-form class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('realms.add_members_to_role_heading', ['role' => $roleName]) }}</flux:heading>
    </div>
    <div class="grid sm:grid-cols-2 gap-6 mb-6">
        <flux:field class="col-span-full">
            <flux:label>{{ __('Add new User') }}</flux:label>
            <flux:pillbox
                multiple
                searchable
                placeholder="{{ __('committees.select_user') }}"
                wire:model="usernames"
            >
                @foreach($users as $user)
                    <flux:pillbox.option value="{{ $user->getFirstAttribute('uid') }}">{{ $user->getFirstAttribute('uid') }} ({{ $user->getFirstAttribute('cn') }})</flux:pillbox.option>
                @endforeach
            </flux:pillbox>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Starting') }}</flux:label>
            <flux:input type="date" wire:model="start_date" wire:change="updateDecisionDate" />
            <flux:error name="start_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Ending') }}</flux:label>
            <flux:input type="date" wire:model="end_date" />
            <flux:error name="end_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Decided') }}</flux:label>
            <flux:input type="date" wire:model="decision_date" />
            <flux:error name="decision_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Comment') }}</flux:label>
            <flux:input wire:model="comment" />
            <flux:error name="comment" />
        </flux:field>
    </div>
</x-livewire-form>
