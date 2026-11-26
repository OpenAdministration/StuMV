<x-profile-layout :realm="$realm_uid" :username="$currentUsername" :name="$givenName.' '.$sn">
    <div class="max-w-7xl mx-auto space-y-6">
        @if($canToggleActive)
            <flux:card>
                <flux:switch wire:model.live="userIsActive" label="{{ __('profile.user_is_active') }}" description="{{ __('profile.user_is_active_description') }}" />
            </flux:card>
        @endif

        @if($canDelete)
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('profile.delete_account') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('profile.delete_account_description') }}</flux:text>
                </div>
                <flux:modal.trigger name="delete-account">
                    <flux:button variant="danger" icon="trash-2">{{ __('profile.delete_account') }}</flux:button>
                </flux:modal.trigger>
            </flux:card>
        @endif
    </div>

    @if($canDelete)
        <form wire:submit="deleteAccount">
            <flux:modal name="delete-account" class="md:w-lg">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg" class="modal-header">{{ __('profile.delete_account_confirm_title') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('profile.delete_account_confirm_warning') }}</flux:text>
                    </div>
                    <flux:input
                        wire:model="deleteConfirmation"
                        :label="__('profile.delete_account_confirmation_label', ['username' => $currentUsername])"
                        autocomplete="off"
                    />
                    <div class="flex justify-end gap-4">
                        <flux:modal.close>
                            <flux:button>{{ __('common.cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" type="submit">{{ __('profile.delete_account') }}</flux:button>
                    </div>
                </div>
            </flux:modal>
        </form>
    @endif
</x-profile-layout>
