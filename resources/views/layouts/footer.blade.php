<footer class="grid xl:grid-cols-3 items-center xl:justify-between opacity-60 px-6 pt-8 pb-4 gap-2 mt-auto text-sm">
    <div class="flex">
        <span class="mx-auto xl:ml-0">Provided under AGPL</span>
    </div>
    <div class="flex">
        <span class="flex space-x-1 items-center mx-auto">
            @if (Config::get('app.name') == 'StuMV')
            <span>{{ __('code with') }}</span>
            <x-fas-heart class="text-red-600"/>
            <span>{{ __('by') }}</span>
            <a class="text-indigo-600" href="https://open-administration.de">Open Administration</a>
            @else
            <span>{{ Config::get('app.name') }} is based on <a href="https://github.com/OpenAdministration/StuMV" target="_blank" rel="noopener noreferrer">StuMV</a>.</span>
        </span>
    </div>
    <div class="flex">
        <span class="flex items-center space-x-5 mx-auto xl:mr-0">
            @if (Config::get('app.about_url') != '')
            <x-link target="_blank" :href="route('about')">{{ __('About') }}</x-link>
            @endif
            @if (Config::get('app.terms_url') != '')
            <x-link target="_blank" :href="route('terms')">{{ __('Terms') }}</x-link>
            @endif
            @if (Config::get('app.privacy_url') != '')
            <x-link target="_blank" :href="route('privacy')">{{ __('Privacy') }}</x-link>
            @endif
        </span>
    </div>
</footer>
