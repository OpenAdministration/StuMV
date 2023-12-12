<form {{ $attributes->merge(['wire:submit' => 'save']) }}>
    {{ $slot }}
    <div class="mt-6 flex items-center justify-end gap-x-6">
        @isset($abort_route)
            <a wire:navigate href="{{ $abort_route }}" class="text-sm font-semibold leading-6 text-gray-900">{{  __('Cancel') }}</a>
        @endisset
        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            {{ __('Save') }}
        </button>
    </div>
</form>
