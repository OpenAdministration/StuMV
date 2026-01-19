<form {{ $attributes->merge(['wire:submit' => 'save']) }}>
    {{ $slot }}
    <div class="mt-6 flex items-center justify-end gap-x-3">
        @isset($abort_route)
            <flux:button icon="ban" wire:navigate href="{{ $abort_route }}">{{  __('Cancel') }}</flux:button>
        @else
            <flux:button icon="ban" wire:navigate href="{{ url()->previous() }}">{{  __('Cancel') }}</flux:button>
        @endisset
        <flux:button variant="primary" icon="save" type="submit">{{ __('Save') }}</flux:button>
    </div>
</form>
