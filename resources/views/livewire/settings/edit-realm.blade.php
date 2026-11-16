<div>
    <x-livewire-form class="max-w-7xl mx-auto w-full">
        <div class="mb-6">
            <flux:heading size="xl" class="mb-4">{{ __('realms.edit_headline', ['name' => $name]) }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.edit_explanation', ['name' => $name]) }}</flux:text>
        </div>
        <div class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <section class="space-y-6 pt-4">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ __('realms.contact_legend') }}</flux:heading>
                    <flux:text>{{ __('realms.contact_description') }}</flux:text>
                </div>
                <div class="space-y-6">
                    <div class="grid grid-cols-[1fr_8rem] gap-4">
                        <flux:input wire:model="street" :label="__('realms.contact_street')" autocomplete="address-line1" />
                        <flux:input wire:model="house_number" :label="__('realms.contact_house_number')" />
                    </div>
                    <div class="grid grid-cols-[8rem_1fr] gap-4">
                        <flux:input wire:model="postal_code" :label="__('realms.contact_postal_code')" autocomplete="postal-code" />
                        <flux:input wire:model="city" :label="__('realms.contact_city')" autocomplete="address-level2" />
                    </div>
                    <flux:input type="email" wire:model="email" :label="__('realms.contact_email')" />
                    <flux:input type="tel" wire:model="phone" :label="__('realms.contact_phone')" />
                    <flux:input type="url" wire:model="website" :label="__('realms.contact_website')" placeholder="https://" />
                </div>
            </section>

            <section class="space-y-6 pt-4">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ __('realms.letter_defaults_legend') }}</flux:heading>
                    <flux:text>{{ __('realms.letter_defaults_description') }}</flux:text>
                </div>
                <flux:input wire:model="letter_sender_unit" :label="__('profile.cover_letter_sender_unit')" :placeholder="__('profile.cover_letter_sender_unit_placeholder')" />
                <flux:input wire:model="letter_subject" :label="__('profile.cover_letter_subject')" />
                <flux:editor wire:model="letter_body" :label="__('profile.cover_letter_body')" toolbar="bold italic underline | bullet ordered" />
            </section>
        </div>
        <x-slot:abort_route>
            {{ url()->previous() }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
