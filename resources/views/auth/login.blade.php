<x-guest-layout>
    <x-auth-card>
        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <!-- Validation Errors -->


        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Address -->
            <x-input.group :label="__('Username')" class="block mt-1 w-full" name="uid" id="uid" :value="old('uid')" required autofocus />

            <!-- Password -->
            <x-input.group type="password" class="block mt-1 w-full" :label="__('Password')" id="password" name="password" required autocomplete="current-password"/>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" name="remember">
                    <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-indigo-500 hover:text-indigo-700" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-button.primary class="ml-3" type="submit">
                    {{ __('Log in') }}
                </x-button.primary>
            </div>
        </form>
        <x-slot:footer class="text-sm space-x-1">
            <span class="text-gray-500">{{ __("Don't have an account?") }}</span>
            <a class="underline text-indigo-600 hover:text-indigo-700" href="{{ route('register') }}">
                {{ __('Sign up and get started!') }}
            </a>
        </x-slot:footer>
    </x-auth-card>
</x-guest-layout>
