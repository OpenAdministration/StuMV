<form {{ $attributes->merge(['wire:submit' => 'save']) }}>
    {{ $slot }}
    <div class="mt-6 flex items-center justify-end gap-x-3">
        @isset($abort_route)
            <flux:button icon="ban" wire:navigate href="{{ $abort_route }}">{{  __('common.cancel') }}</flux:button>
        @else
            <flux:button icon="ban" wire:navigate href="{{ url()->previous() }}">{{  __('common.cancel') }}</flux:button>
        @endisset
        <flux:button variant="primary" icon="{{ $submit_icon ?? 'save' }}" type="submit">{{ $submit_label ?? __('common.save') }}</flux:button>
    </div>
</form>
