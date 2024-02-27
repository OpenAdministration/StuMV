<div class="flex-col space-y-4">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('groups.roles_add_headline', ['name' => $group_cn]) }}</h1>
            <p class="mt-2 text-sm text-gray-700">
                {{  __('groups.roles_add_explanation', ['name' => $group_cn]) }}
            </p>
        </div>
    </div>
    <x-livewire-form>
        <x-select :label="__('Committee')" wire:model.live="selected_committee_dn">
            @foreach($committees as $committee)
                <option value="{{ $committee->getDn() }}">
                    {{ $committee->getFirstAttribute('description') }} ({{ $committee->getFirstAttribute('ou') }})
                </option>
            @endforeach
        </x-select>
        <x-select :label="__('Role')" wire:model="selected_role_dn" :disabled="empty($selected_committee_dn)">
            @foreach($roles as $role)
                <option value="{{ $role->getDn() }}">
                    {{ $role->getFirstAttribute('description') }} ({{ $role->getFirstAttribute('cn') }})
                </option>
            @endforeach
        </x-select>
    </x-livewire-form>
</div>
