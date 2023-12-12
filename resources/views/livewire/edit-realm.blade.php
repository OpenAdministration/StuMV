<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.edit_headline', ['name' => $name]) }}</h1>
            <p class="mt-2 text-sm text-gray-700">
                {{ __('realms.edit_explanation', ['name' => $name]) }}
            </p>
        </div>
    </div>
    <x-input.group wire:model.live="name">
        <x-slot:label>{{ __('Name') }}</x-slot:label>
    </x-input.group>
    <x-slot:abort_route>
        {{ url()->previous() }}
    </x-slot:abort_route>
</x-livewire-form>
