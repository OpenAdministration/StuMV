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
              <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 4h1a1 1 0 00-1-1v1zm-1 12a1 1 0 102 0h-2zM8 3a1 1 0 000 2V3zM3.293 19.293a1 1 0 101.414 1.414l-1.414-1.414zM19 4v12h2V4h-2zm1-1H8v2h12V3zm-.707.293l-16 16 1.414 1.414 16-16-1.414-1.414z" />
              </svg>
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
              <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 4h1a1 1 0 00-1-1v1zm-1 12a1 1 0 102 0h-2zM8 3a1 1 0 000 2V3zM3.293 19.293a1 1 0 101.414 1.414l-1.414-1.414zM19 4v12h2V4h-2zm1-1H8v2h12V3zm-.707.293l-16 16 1.414 1.414 16-16-1.414-1.414z" />
              </svg>
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
                    <a href="#" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        {{ __('realms.dashboard.admin_headline') }}
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('realms.dashboard.admin_explanation') }}</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
              <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 4h1a1 1 0 00-1-1v1zm-1 12a1 1 0 102 0h-2zM8 3a1 1 0 000 2V3zM3.293 19.293a1 1 0 101.414 1.414l-1.414-1.414zM19 4v12h2V4h-2zm1-1H8v2h12V3zm-.707.293l-16 16 1.414 1.414 16-16-1.414-1.414z" />
              </svg>
            </span>
        </div>
        <div class="group relative bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-500">
            <div>
              <span class="inline-flex rounded-lg p-3 bg-yellow-50 text-yellow-700 ring-4 ring-white">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                </svg>
              </span>
            </div>
            <div class="mt-8">
                <h3 class="text-base font-semibold leading-6 text-gray-900">
                    <a href="#" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        Payroll
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">Doloribus dolores nostrum quia qui natus officia quod et dolorem. Sit repellendus qui ut at blanditiis et quo et molestiae.</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
              <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 4h1a1 1 0 00-1-1v1zm-1 12a1 1 0 102 0h-2zM8 3a1 1 0 000 2V3zM3.293 19.293a1 1 0 101.414 1.414l-1.414-1.414zM19 4v12h2V4h-2zm1-1H8v2h12V3zm-.707.293l-16 16 1.414 1.414 16-16-1.414-1.414z" />
              </svg>
            </span>
        </div>
        <div class="sm:rounded-bl-lg group relative bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-500">
            <div>
              <span class="inline-flex rounded-lg p-3 bg-rose-50 text-rose-700 ring-4 ring-white">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9.75h4.875a2.625 2.625 0 010 5.25H12M8.25 9.75L10.5 7.5M8.25 9.75L10.5 12m9-7.243V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z" />
                </svg>
              </span>
            </div>
            <div class="mt-8">
                <h3 class="text-base font-semibold leading-6 text-gray-900">
                    <a href="#" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        Submit an expense
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">Doloribus dolores nostrum quia qui natus officia quod et dolorem. Sit repellendus qui ut at blanditiis et quo et molestiae.</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
              <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 4h1a1 1 0 00-1-1v1zm-1 12a1 1 0 102 0h-2zM8 3a1 1 0 000 2V3zM3.293 19.293a1 1 0 101.414 1.414l-1.414-1.414zM19 4v12h2V4h-2zm1-1H8v2h12V3zm-.707.293l-16 16 1.414 1.414 16-16-1.414-1.414z" />
              </svg>
            </span>
        </div>
        <div class="rounded-bl-lg rounded-br-lg sm:rounded-bl-none group relative bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-indigo-500">
            <div>
              <span class="inline-flex rounded-lg p-3 bg-indigo-50 text-indigo-700 ring-4 ring-white">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                </svg>
              </span>
            </div>
            <div class="mt-8">
                <h3 class="text-base font-semibold leading-6 text-gray-900">
                    <a href="#" class="focus:outline-none">
                        <!-- Extend touch target to entire panel -->
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        Training
                    </a>
                </h3>
                <p class="mt-2 text-sm text-gray-500">Doloribus dolores nostrum quia qui natus officia quod et dolorem. Sit repellendus qui ut at blanditiis et quo et molestiae.</p>
            </div>
            <span class="pointer-events-none absolute right-6 top-6 text-gray-300 group-hover:text-gray-400" aria-hidden="true">
      <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
        <path d="M20 4h1a1 1 0 00-1-1v1zm-1 12a1 1 0 102 0h-2zM8 3a1 1 0 000 2V3zM3.293 19.293a1 1 0 101.414 1.414l-1.414-1.414zM19 4v12h2V4h-2zm1-1H8v2h12V3zm-.707.293l-16 16 1.414 1.414 16-16-1.414-1.414z" />
      </svg>
    </span>
        </div>
    </div>

</div>
