<div>
    <x-livewire-form class="max-w-7xl mx-auto w-full">
        <div class="mb-6">
            <flux:heading size="xl" class="mb-4">{{ __('realms.membership_export.headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.letter_defaults_description') }}</flux:text>
        </div>
        <div class="space-y-6">
            <flux:switch wire:model="withCoverLetter" :label="__('profile.cover_letter_toggle')" align="left" />
            <div class="grid gap-6 md:grid-cols-2 items-start">
                <flux:fieldset>
                    <legend>{{ __('profile.cover_letter_recipient') }}</legend>
                    <div class="space-y-4 p-4">
                        <flux:input wire:model="recipient.organisation" :label="__('profile.cover_letter_recipient_organisation')" />
                        <flux:input wire:model="recipient.name" :label="__('profile.cover_letter_recipient_name')" />
                        <div class="grid grid-cols-[1fr_8rem] gap-4">
                            <flux:input wire:model="recipient.street" :label="__('realms.contact_street')" />
                            <flux:input wire:model="recipient.house_number" :label="__('realms.contact_house_number')" />
                        </div>
                        <div class="grid grid-cols-[8rem_1fr] gap-4">
                            <flux:input wire:model="recipient.postal_code" :label="__('realms.contact_postal_code')" />
                            <flux:input wire:model="recipient.city" :label="__('realms.contact_city')" />
                        </div>
                        <flux:input wire:model="recipient.country" :label="__('profile.cover_letter_recipient_country')" />
                    </div>
                </flux:fieldset>
                <flux:fieldset>
                    <legend>{{ __('profile.cover_letter_sender') }}</legend>
                    <div class="space-y-4 p-4">
                        <flux:input wire:model="senderName" :label="__('profile.cover_letter_sender_name')" :placeholder="$realmName" />
                        <flux:input wire:model="senderUnit" :label="__('profile.cover_letter_sender_unit')" :placeholder="__('profile.cover_letter_sender_unit_placeholder')" />
                        <div class="grid grid-cols-[1fr_8rem] gap-4">
                            <flux:input wire:model="senderBuilding" :label="__('realms.contact_office_building')" :placeholder="$contactBuilding" />
                            <flux:input wire:model="senderRoom" :label="__('realms.contact_office_room')" :placeholder="$contactRoom" />
                        </div>
                        <flux:input type="email" wire:model="senderEmail" :label="__('profile.cover_letter_sender_email')" :placeholder="$contactEmail" />
                        <flux:input type="tel" wire:model="senderPhone" :label="__('profile.cover_letter_sender_phone')" :placeholder="$contactPhone" />
                        <flux:input type="url" wire:model="senderWebsite" :label="__('profile.cover_letter_sender_website')" placeholder="https://" />
                        <flux:input wire:model="signerFunction" :label="__('profile.cover_letter_signer_function')" />
                    </div>
                </flux:fieldset>
            </div>
            <flux:fieldset>
                <legend>{{ __('profile.cover_letter_content') }}</legend>
                <div class="space-y-4 p-4">
                    <flux:input wire:model="subject" :label="__('profile.cover_letter_subject')" />
                    <flux:editor wire:model="letterBody" :label="__('profile.cover_letter_body')" toolbar="bold italic underline | bullet ordered" />
                </div>
            </flux:fieldset>
        </div>
        <x-slot:abort_route>
            {{ url()->previous() }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
