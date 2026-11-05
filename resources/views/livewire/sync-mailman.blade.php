<div>
    <flux:modal.trigger name="sync-mailman-confirm">
        <flux:button
            variant="ghost"
            icon="mail-pen"
            title="{{ __('sync.mailman_title') }}"
        />
    </flux:modal.trigger>

    <flux:modal name="sync-mailman-confirm" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="modal-header">{{ __('sync.mailman_title') }}</flux:heading>
                <flux:text class="mt-2">{{ __('sync.mailman_confirm_text') }}</flux:text>
            </div>

            <div class="flex justify-end gap-4">
                <flux:button icon="ban" x-on:click="$flux.modal('sync-mailman-confirm').close()">{{ __('common.cancel') }}</flux:button>
                <flux:button variant="primary" icon="mail-pen" wire:click="syncMailman">{{ __('sync.confirm') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
