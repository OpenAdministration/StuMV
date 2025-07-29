<div>
    <div class="w-full">
        <div class="mb-4 -mx-6 -mt-6 px-6 flex border-b border-zinc-200 gap-3">
            <a wire:navigate href="{{ route('profile', ['username' => $currentUsername]) }}" class="inline-flex items-center gap-x-1.5 px-2 pt-4 pb-3 border-b-2 border-indigo-400 font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-indigo-400 focus:outline-none focus:text-gray-700 focus:border-indigo-400 transition duration-150 ease-in-out">{{ __('Profile') }}</a>
            <a wire:navigate href="{{ route('profile.memberships', ['username' => $currentUsername]) }}" class="inline-flex items-center gap-x-1.5 px-2 pt-4 pb-3 border-b-2 border-transparent font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">{{ __('profile.memberships') }}</a>
            <a wire:navigate href="{{ route('password.change', ['username' => $currentUsername]) }}" class="inline-flex items-center gap-x-1.5 px-2 pt-4 pb-3 border-b-2 border-transparent font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">{{ __('Change Password') }}</a>
        </div>
    </div>

    <x-livewire-form :abort_route="null" wire:submit="save">
        <div class="mt-6 sm:mt-5">
            <x-input.group disabled :label="__('Username')" wire:model="uid">
                <x-slot:help>{{ __('validation.disabled', ['attribute' => 'username']) }}</x-slot:help>
            </x-input.group>
            <x-input.group disabled type="email" :label="__('E-Mail')" wire:model.live="email">
                <x-slot:help>{{ __('validation.disabled', ['attribute' => 'email']) }}</x-slot:help>
            </x-input.group>
            <x-input.group :label="__('Name')" wire:model.live="fullName"/>
        </div>
        <x-slot:abort_route>
            {{ url()->previous() }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
