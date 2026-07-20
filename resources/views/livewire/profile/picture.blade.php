<div class="max-w-[calc(100%_+_4rem)]! w-[calc(100%_+_3rem)]! sm:w-[calc(100%_+_4rem)]! flex flex-col -m-6! sm:-m-8!">
    <div class="pt-6 sm:pt-8 px-6 sm:px-8 pb-3">
        <flux:heading size="xl" class="max-w-7xl mx-auto">{{ $givenName }} {{ $sn }}</flux:heading>
    </div>

    <x-navbar-profile :realm="$realm_uid" :username="$currentUsername" />

    <div class="flex-1 p-6 sm:p-8 overflow-y-auto">
        <div class="max-w-7xl mx-auto space-y-6">
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('profile.note_profile_pictures_public') }}" />

            <div x-data="cropper">
                <div>
                    @if($avatarID)
                        <img class="h-[15rem] xl:h-[20rem] rounded-md shadow-sm border border-zinc-200 dark:border-zinc-700" src="{{ asset('storage/avatars/' . $avatarID . '.webp') }}" alt="Profile picture of {{ $givenName }} {{ $sn }}">
                    @elseif($upload && $upload->isPreviewable())
                        <img
                            wire:key="cropper-image"
                            x-ref="image"
                            x-init="initCropper()"
                            src="{{ $upload->temporaryUrl() }}"
                            class="h-[15rem] xl:h-[20rem]"
                        >
                    @else
                        <flux:file-upload wire:model="upload" accept="image/*">
                            <flux:file-upload.dropzone
                                :heading="__('common.drop_file_here')"
                                text="JPEG, PNG, WebP"
                                class="h-[15rem] xl:h-[20rem]"
                            />
                        </flux:file-upload>
                        <flux:error name="upload" />
                    @endif
                </div>
                <div class="mt-6 flex items-center justify-end gap-x-3">
                    @if($avatarID)
                        <flux:button
                            variant="danger"
                            icon="trash-2"
                            wire:click="deletePicture"
                        >
                            {{ __('profile.remove_picture') }}
                        </flux:button>
                    @elseif($upload && $upload->isPreviewable())
                        <flux:button
                            icon="ban"
                            wire:click="cancelUpload"
                            @click="destroyCropper()"
                        >
                            {{ __('common.cancel') }}
                        </flux:button>
                        <flux:button
                            variant="primary"
                            icon="save"
                            @click="cropPicture()"
                        >
                            {{ __('common.save') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
Alpine.data('cropper', () => {
    return {
        cropper: null,
        initCropper() {
            this.destroyCropper();

            this.cropper = new Cropper(this.$refs.image, {
                aspectRatio: 1 / 1,
                zoomable: false,
                // Keep the crop box from being dragged/resized past the
                // image's own edges, into the empty canvas area.
                viewMode: 1,
            });
        },
        destroyCropper() {
            if (this.cropper != null) {
                this.cropper.destroy();
                this.cropper = null;
            }
        },
        cropPicture() {
            // Rounded, in the original (uploaded) image's own pixel
            // coordinates - matches Illuminate\Image\Image::crop()'s
            // (width, height, x, y) signature, applied server-side to the
            // originally uploaded file rather than a client-exported canvas.
            const data = this.cropper.getData(true);

            $wire.set('cropX', data.x);
            $wire.set('cropY', data.y);
            $wire.set('cropWidth', data.width);
            $wire.set('cropHeight', data.height);

            this.destroyCropper();

            $wire.savePicture();
        },
    }
})
</script>
@endscript
