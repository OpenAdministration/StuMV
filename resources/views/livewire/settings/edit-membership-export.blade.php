<div>
    <x-livewire-form class="max-w-7xl mx-auto w-full">
        <div class="mb-6">
            <flux:heading size="xl" class="mb-4">{{ __('realms.membership_export.headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.letter_defaults_description') }}</flux:text>
        </div>
        <div class="space-y-6">
            <flux:input wire:model="letter_sender_unit" :label="__('profile.cover_letter_sender_unit')" :placeholder="__('profile.cover_letter_sender_unit_placeholder')" />
            <flux:input wire:model="letter_subject" :label="__('profile.cover_letter_subject')" />
            <flux:editor wire:model="letter_body" :label="__('profile.cover_letter_body')" toolbar="bold italic underline | bullet ordered" />
        </div>
        <x-slot:abort_route>
            {{ url()->previous() }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
