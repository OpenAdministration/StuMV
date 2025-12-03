<x-livewire-form>
    <div class="w-full">
        <div class="mb-4 -mx-6 -mt-6 px-6 flex border-b border-zinc-200 gap-3">
            <a wire:navigate href="{{ route('profile', ['username' => $currentUsername]) }}" class="inline-flex items-center gap-x-1.5 px-2 pt-4 pb-3 border-b-2 border-transparent font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">{{ __('Profile') }}</a>
            <a wire:navigate href="{{ route('profile.memberships', ['username' => $currentUsername]) }}" class="inline-flex items-center gap-x-1.5 px-2 pt-4 pb-3 border-b-2 border-transparent font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">{{ __('profile.memberships') }}</a>
            <a wire:navigate href="{{ route('password.change', ['username' => $currentUsername]) }}" class="inline-flex items-center gap-x-1.5 px-2 pt-4 pb-3 border-b-2 border-indigo-400 font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-indigo-400 focus:outline-none focus:text-gray-700 focus:border-indigo-400 transition duration-150 ease-in-out">{{ __('Change Password') }}</a>
        </div>
    </div>

    <x-input.group type="password" wire:model="password" :label="__('Password')">
        <x-slot:help>{{ __('user.help.password') }}</x-slot:help>
    </x-input.group>
    <x-input.group type="password" wire:model="password_confirmation" :label="__('Password confirm')"/>
    <x-slot:abort_route>{{ back() }}</x-slot:abort_route>
</x-livewire-form>
