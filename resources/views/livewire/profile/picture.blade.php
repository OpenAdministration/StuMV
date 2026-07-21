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
                        <cropper-canvas
                            wire:key="cropper-image"
                            x-ref="canvas"
                            x-init="initCropper()"
                            background
                            class="h-[15rem] xl:h-[20rem]"
                        >
                            <cropper-image src="{{ $upload->temporaryUrl() }}"></cropper-image>
                            <cropper-shade hidden></cropper-shade>
                            <cropper-handle action="select" plain></cropper-handle>
                            <cropper-selection
                                x-ref="selection"
                                initial-coverage="0.5"
                                aspect-ratio="1"
                                movable
                                resizable
                            >
                                <cropper-grid role="grid" bordered covered></cropper-grid>
                                <cropper-crosshair centered></cropper-crosshair>
                                <cropper-handle action="move" theme-color="rgba(255, 255, 255, 0.35)"></cropper-handle>
                                <cropper-handle action="n-resize"></cropper-handle>
                                <cropper-handle action="e-resize"></cropper-handle>
                                <cropper-handle action="s-resize"></cropper-handle>
                                <cropper-handle action="w-resize"></cropper-handle>
                                <cropper-handle action="ne-resize"></cropper-handle>
                                <cropper-handle action="nw-resize"></cropper-handle>
                                <cropper-handle action="se-resize"></cropper-handle>
                                <cropper-handle action="sw-resize"></cropper-handle>
                            </cropper-selection>
                        </cropper-canvas>
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
                        >
                            {{ __('common.cancel') }}
                        </flux:button>
                        <flux:button
                            variant="primary"
                            icon="save"
                            loading
                            x-bind:data-loading="saving || null"
                            x-bind:disabled="saving"
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
        saving: false,
        initCropper() {
            const selection = this.$refs.selection;

            // Keep the crop box from being dragged/resized past the
            // image's own edges, into the empty canvas area (cropperjs
            // v2 no longer has a built-in equivalent of v1's viewMode).
            let clamping = false;

            selection.addEventListener('change', (event) => {
                if (clamping) {
                    return;
                }

                const image = this.$refs.canvas.querySelector('cropper-image');
                const canvasRect = this.$refs.canvas.getBoundingClientRect();
                const imageRect = image.getBoundingClientRect();

                if (imageRect.width === 0 || imageRect.height === 0) {
                    return;
                }

                const minX = imageRect.left - canvasRect.left;
                const minY = imageRect.top - canvasRect.top;
                const maxX = minX + imageRect.width;
                const maxY = minY + imageRect.height;

                let { x, y, width, height } = event.detail;

                // Shrink both dimensions by the same factor so the fixed
                // aspect ratio survives - shrinking width/height independently
                // would make cropperjs re-expand them right back to fit the
                // ratio, fighting this listener forever.
                const scale = Math.min(1, imageRect.width / width, imageRect.height / height);
                width *= scale;
                height *= scale;
                x = Math.min(Math.max(x, minX), maxX - width);
                y = Math.min(Math.max(y, minY), maxY - height);

                if (x !== event.detail.x || y !== event.detail.y || width !== event.detail.width || height !== event.detail.height) {
                    event.preventDefault();
                    clamping = true;
                    selection.$change(x, y, width, height);
                    clamping = false;
                }
            });
        },
        cropPicture() {
            if (this.saving) {
                return;
            }
            this.saving = true;

            const selection = this.$refs.selection;
            const image = this.$refs.canvas.querySelector('cropper-image');
            const imageRect = image.getBoundingClientRect();
            const canvasRect = this.$refs.canvas.getBoundingClientRect();

            // Converted from the displayed (scaled) size back to the
            // original (uploaded) image's own pixel coordinates - matches
            // Illuminate\Image\Image::crop()'s (width, height, x, y)
            // signature, applied server-side to the originally uploaded
            // file rather than a client-exported canvas.
            const scaleX = image.$image.naturalWidth / imageRect.width;
            const scaleY = image.$image.naturalHeight / imageRect.height;
            const offsetX = imageRect.left - canvasRect.left;
            const offsetY = imageRect.top - canvasRect.top;

            $wire.set('cropX', Math.round((selection.x - offsetX) * scaleX));
            $wire.set('cropY', Math.round((selection.y - offsetY) * scaleY));
            $wire.set('cropWidth', Math.round(selection.width * scaleX));
            $wire.set('cropHeight', Math.round(selection.height * scaleY));

            $wire.savePicture().finally(() => {
                this.saving = false;
            });
        },
    }
})
</script>
@endscript
