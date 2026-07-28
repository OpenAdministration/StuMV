<flux:field>
    <flux:label>{{ __('oidc_clients.logo') }}</flux:label>
    <flux:description>{{ __('oidc_clients.logo_description') }}</flux:description>
    @if($logoId)
        <div class="flex items-center gap-4">
            <img class="w-24 h-24 p-2 object-contain rounded-md border border-zinc-200 dark:border-zinc-700" src="{{ asset('storage/oidc-client-logos/'.$logoId) }}" alt="{{ __('oidc_clients.logo') }}">
            <flux:button variant="danger" icon="trash-2" wire:click="removeLogo">{{ __('oidc_clients.remove_logo') }}</flux:button>
        </div>
    @else
        <flux:file-upload wire:model="logo" accept="image/*">
            <flux:file-upload.dropzone
                :heading="__('common.drop_file_here')"
                text="JPEG, PNG, WebP, SVG"
            />
        </flux:file-upload>
        <flux:error name="logo" />
    @endif
</flux:field>
