<div>
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.dashboard.headline') }}</h1>
        <p class="mt-2 text-sm text-gray-700">{{ __('realms.dashboard.explanation') }}</p>
    </div>
    <div class="-mx-6 -mb-6 mt-6 divide-y divide-gray-200 overflow-hidden rounded-lg bg-gray-200 sm:grid sm:grid-cols-2 sm:gap-px sm:divide-y-0">
        <div class="rounded-tl-lg rounded-tr-lg sm:rounded-tr-none group relative bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-500">
            <div>
              <span class="inline-flex rounded-lg p-4 bg-teal-50 text-teal-700 ring-4 ring-white">
                <x-fas-user class="h-5 w-5"/>
              </span>
            </div>
            <div class="mt-8">
                <h3 class="text-base font-semibold leading-6 text-gray-900">
                    <a href="{{ route('realms.members', $uid) }}" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        {{ __('realms.dashboard.members_heading') }}
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('realms.dashboard.members_explanation') }}</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
              <x-fas-chevron-right class="h-10 w-10"/>
            </span>
        </div>
        <div class="sm:rounded-tr-lg group relative bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-500">
            <div>
              <span class="inline-flex rounded-lg p-4 bg-purple-50 text-purple-700 ring-4 ring-white">
                  <x-fas-user-graduate class="w-5 h-5"/>
              </span>
            </div>
            <div class="mt-8">
                <h3 class="text-base font-semibold leading-6 text-gray-900">
                    <a href="{{ route('realms.mods', $uid) }}" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        {{ __('realms.dashboard.mods_headline') }}
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('realms.dashboard.mods_explanation') }}</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
                <x-fas-chevron-right class="h-10 w-10"/>
            </span>
        </div>
        <div class="group relative bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-500">
            <div>
              <span class="inline-flex rounded-lg p-4 bg-sky-50 text-sky-700 ring-4 ring-white">
                 <x-fas-laptop-code class="w-5 h-5"/>
              </span>
            </div>
            <div class="mt-8">
                <h3 class="text-base font-semibold leading-6 text-gray-900">
                    <a href="{{ route('realms.admins', $uid) }}" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        {{ __('realms.dashboard.admin_headline') }}
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('realms.dashboard.admin_explanation') }}</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
                <x-fas-chevron-right class="h-10 w-10"/>
            </span>
        </div>
        <div class="group relative bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-500">
            <div>
              <span class="inline-flex rounded-lg p-4 bg-yellow-50 text-yellow-700 ring-4 ring-white">
                <x-fas-pencil class="w-5 h-5"/>
              </span>
            </div>
            <div class="mt-8">
                <h3 class="text-base font-semibold leading-6 text-gray-900">
                    <a href="{{ route('realms.edit', $uid) }}" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        {{ __('realms.dashboard.realms_edit_headline') }}
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('realms.dashboard.realms_edit_explanation') }}</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
                <x-fas-chevron-right class="h-10 w-10"/>
            </span>
        </div>
        <div class="sm:rounded-bl-lg group relative bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-500">
            <div>
              <span class="inline-flex rounded-lg p-4 bg-rose-50 text-rose-700 ring-4 ring-white">
                <x-fas-user-group class="h-5 w-5"/>
              </span>
            </div>
            <div class="mt-8">
                <h3 class="text-base font-semibold leading-6 text-gray-900">
                    <a href="{{ route('realms.groups', $uid) }}" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        {{ __('realms.dashboard.groups_headline') }}
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('realms.dashboard.groups_explanation') }}</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
                <x-fas-chevron-right class="h-10 w-10"/>
            </span>
        </div>
        <div class="rounded-bl-lg rounded-br-lg sm:rounded-bl-none group relative bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-500">
            <div>
              <span class="inline-flex rounded-lg p-4 bg-indigo-50 text-indigo-700 ring-4 ring-white">
                <x-fas-wifi class="h-5 w-5"/>
              </span>
            </div>
            <div class="mt-8">
                <h3 class="text-base font-semibold leading-6 text-gray-900">
                    <a href="{{ route('realms.domains', $uid) }}" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        {{ __('realms.dashboard.domains_headline') }}
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('realms.dashboard.domains_explanation') }}</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
                <x-fas-chevron-right class="h-10 w-10"/>
            </span>
        </div>
    </div>

</div>
