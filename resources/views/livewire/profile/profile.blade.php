<div class="max-w-[calc(100%_+_4rem)]! w-[calc(100%_+_3rem)]! sm:w-[calc(100%_+_4rem)]! flex flex-col -m-6! sm:-m-8!">
    <div class="pt-6 sm:pt-8 px-6 sm:px-8 pb-3">
        <flux:heading size="xl" class="max-w-7xl mx-auto">{{ $givenName }} {{ $sn }}</flux:heading>
    </div>

    <x-navbar-profile :realm="$realm_uid" :username="$currentUsername" />

    <div class="flex-1 p-6 sm:p-8 overflow-y-auto">
        <div class="max-w-7xl mx-auto space-y-6">
            <x-livewire-form :abort_route="null" wire:submit="save">
                <div class="grid lg:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>{{ __('common.username') }}</flux:label>
                        <flux:input wire:model="uid" disabled />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('common.email') }}</flux:label>
                        <div class="space-y-2">
                            <div class="flex gap-2 items-center">
                                <flux:input wire:model="email" disabled class="flex-1" />
                                <flux:button
                                    type="button"
                                    icon="plus"
                                    wire:click="addEmailRow"
                                    aria-label="{{ __('profile.emails_add') }}"
                                />
                            </div>
                            @foreach($additionalEmails as $index => $address)
                                <div class="flex gap-2 items-start" wire:key="additional-email-{{ $index }}">
                                    <div class="flex-1">
                                        <flux:input wire:model="additionalEmails.{{ $index }}" type="email">
                                            @if($address !== '' && ! in_array($address, $verifiedAddresses, true))
                                                <x-slot:iconTrailing>
                                                    <flux:badge size="sm" color="amber">{{ __('profile.emails_unverified') }}</flux:badge>
                                                </x-slot:iconTrailing>
                                            @endif
                                        </flux:input>
                                        <flux:error name="additionalEmails.{{ $index }}" />
                                    </div>
                                    <flux:button
                                        type="button"
                                        icon="minus"
                                        wire:click="removeEmailRow({{ $index }})"
                                        aria-label="{{ __('profile.emails_remove') }}"
                                    />
                                </div>
                            @endforeach
                        </div>
                    </flux:field>
                </div>
                <div class="grid lg:grid-cols-2 gap-6 mt-6">
                    <flux:field>
                        <flux:label>{{ __('user.first_name_label') }}</flux:label>
                        <flux:input wire:model="givenName" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('user.last_name_label') }}</flux:label>
                        <flux:input wire:model="sn" />
                    </flux:field>
                </div>
                <div class="grid lg:grid-cols-2 gap-6 mt-6">
                    <flux:field>
                        <flux:label>{{ __('profile.course_label') }}</flux:label>
                        <flux:input wire:model="course" />
                    </flux:field>
                </div>
                <div class="grid lg:grid-cols-2 gap-6 mt-6">
                    <flux:field>
                        <flux:label>{{ __('profile.street_label') }}</flux:label>
                        <flux:input wire:model="street" />
                    </flux:field>
                    <div class="grid grid-cols-[1fr_2fr] gap-6">
                        <flux:field>
                            <flux:label>{{ __('profile.postal_code_label') }}</flux:label>
                            <flux:input wire:model="postalCode" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('profile.city_label') }}</flux:label>
                            <flux:input wire:model="city" />
                        </flux:field>
                    </div>
                </div>
                <div class="grid lg:grid-cols-2 gap-6 mt-6">
                    <flux:field>
                        <flux:label>{{ __('profile.phone_label') }}</flux:label>
                        <flux:input wire:model="phone" />
                    </flux:field>
                </div>
                @can('superadmin', \App\Models\User::class)
                    <div class="mt-6 space-y-4">
                        <flux:separator variant="subtle" />
                        <flux:switch wire:model.live="userIsActive" label="{{ __('profile.user_is_active') }}" description="{{ __('profile.user_is_active_description') }}" />
                        <flux:separator variant="subtle" />
                    </div>
                @endcan
                <x-slot:abort_route>
                    {{ url()->previous() }}
                </x-slot:abort_route>
            </x-livewire-form>
        </div>
    </div>
</div>
