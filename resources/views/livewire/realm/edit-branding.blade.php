<div class="max-w-6xl mx-auto w-full">
    <div class="mb-8">
        <flux:heading size="xl" class="mb-4">{{ __('realms.branding.headline') }}</flux:heading>
        <flux:text class="text-base">{{ __('realms.branding.explanation') }}</flux:text>
    </div>

    <div class="grid md:grid-cols-2 gap-6 pb-8">
        <flux:fieldset>
            <legend>{{ __('realms.branding.logo_heading') }}</legend>
            
            <div class="p-4">
                @if($logoID)
                    <img class="w-full h-[12rem] p-2 object-contain rounded-md border border-zinc-200 dark:border-zinc-700" src="{{ asset('storage/realm-branding/' . $logoID) }}" alt="{{ __('realms.branding.logo_heading') }}">
                @else
                    <flux:file-upload wire:model="logo" accept="image/*">
                        <flux:file-upload.dropzone
                            :heading="__('common.drop_file_here')"
                            text="JPEG, PNG, WebP, SVG"
                            class="h-[12rem]"
                        />
                    </flux:file-upload>
                    <flux:error name="logo" />
                @endif

                @if($logoID)
                    <div class="flex items-center justify-end pt-4 mt-auto">
                        <flux:button variant="danger" icon="trash-2" wire:click="deleteLogo">{{ __('realms.branding.remove_logo') }}</flux:button>
                    </div>
                @endif
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <legend>{{ __('realms.branding.background_heading') }}</legend>

            <div class="p-4">
                @if($backgroundID)
                    <img class="w-full h-[12rem] p-2 object-contain rounded-md border border-zinc-200 dark:border-zinc-700" src="{{ asset('storage/realm-branding/' . $backgroundID) }}" alt="{{ __('realms.branding.background_heading') }}">
                @else
                    <flux:file-upload wire:model="background" accept="image/*">
                        <flux:file-upload.dropzone
                            :heading="__('common.drop_file_here')"
                            text="JPEG, PNG, WebP"
                            class="h-[12rem]"
                        />
                    </flux:file-upload>
                    <flux:error name="background" />
                @endif

                @if($backgroundID)
                    <div class="flex items-center justify-end pt-4 mt-auto">
                        <flux:button variant="danger" icon="trash-2" wire:click="deleteBackground">{{ __('realms.branding.remove_background') }}</flux:button>
                    </div>
                @endif
            </div>
        </flux:fieldset>
    </div>
</div>
