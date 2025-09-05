<form {{ $attributes->merge(['wire:submit' => 'save']) }}>
    {{ $slot }}
    <div class="mt-6 flex items-center justify-end gap-x-3">
        @isset($abort_route)
            <flux:button wire:navigate href="{{ $abort_route }}">{{  __('Cancel') }}</flux:button>
        @else
            <flux:button wire:navigate href="{{ url()->previous() }}">{{  __('Cancel') }}</flux:button>
        @endisset
        <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
    </div>
</form>
