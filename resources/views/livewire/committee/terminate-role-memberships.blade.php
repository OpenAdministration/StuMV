<x-livewire-form class="max-w-6xl mx-auto w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('roles.terminate_role_memberships_title', ['role' => $role->getFirstAttribute('description')]) }}</flux:heading>
    </div>
    <div class="grid gap-6 mb-6">
        <flux:field class="col-span-full">
            <flux:label>{{ __('roles.users') }}</flux:label>
            <flux:pillbox
                multiple
                searchable
                placeholder="{{ __('committees.select_user') }}"
                wire:model="membershipsToTerminate"
            >
                @foreach($memberships as $m)
                    <flux:pillbox.option value="{{ $m->id }}">
                        {{ \App\Ldap\User::findOrFailByUsername($m->username)->getFirstAttribute('cn') }} ({{ $m->username }})
                    </flux:pillbox.option>
                @endforeach
            </flux:pillbox>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('roles.termination_date_label') }}</flux:label>
            <flux:input type="date" wire:model="terminationDate" />
            <flux:error name="terminationDate" />
        </flux:field>
    </div>
</x-livewire-form>
