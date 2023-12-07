<footer class="flex items-center justify-between opacity-60 mx-5 my-1.5 text-sm">
    <div class="">Provided under AGPL</div>
    <div class="flex space-x-1 items-center">
        <span>{{ __('code with') }}</span>
        <x-fas-heart class="text-red-600"/>
        <span>{{ __('by') }}</span>
        <a class="text-indigo-600" href="https://open-administration.de">Open Administration</a>
    </div>
    <div class="flex items-center space-x-5">
        <x-link target="_blank" :href="route('about')">{{ __('About') }}</x-link>
        <x-link target="_blank" :href="route('terms')">{{ __('Terms') }}</x-link>
        <x-link target="_blank" :href="route('privacy')">{{ __('Privacy') }}</x-link>
    </div>
</footer>
