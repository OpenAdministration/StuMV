<div class="max-w-[calc(100%_+_4rem)]! w-[calc(100%_+_3rem)]! sm:w-[calc(100%_+_4rem)]! flex flex-col -m-6! sm:-m-8!">
    <div class="pt-6 sm:pt-8 px-6 sm:px-8 pb-3">
        <flux:heading size="xl">{{ $givenName }} {{ $sn }}</flux:heading>
    </div>
    
    <x-navbar-profile :username="$currentUsername" />

    <div class="flex-1 p-6 sm:p-8 overflow-y-auto">
        <div class="max-w-6xl mx-auto space-y-6">
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('profile.noteProfilePicturesPublic') }}" />

            <div x-data="cropper">
                <div>
                    @if($avatarID)
                        <img class="h-[15rem] rounded-md shadow-sm border border-zinc-200" src="{{ asset('storage/avatars/' . $avatarID . '.jpg') }}" alt="Profile picture of {{ $givenName }} {{ $sn }}">
                    @else
                        <input
                            id="imageInput"
                            type="file"
                            accept="image/*"
                            class="w-full h-[15rem] px-3 py-2 border border-zinc-200 rounded-md cursor-pointer"
                            :value="imageCropped"
                            x-show="!imageIsSelected"
                            x-on:change="loadImage"
                        >
                        <img id="image" class="h-[15rem]" x-show="imageIsSelected">
                    @endif
                </div>
                <div class="mt-6 flex items-center justify-end gap-x-6">
                    @if($avatarID)
                        <flux:button variant="danger" wire:click="deletePicture">
                            {{ __('Entfernen') }}
                        </flux:button>
                    @else
                        <flux:button @click="cancelPicture">
                            {{ __('Abbrechen') }}
                        </flux:button>
                        <flux:button variant="primary" @click="cropPicture">
                            {{ __('Speichern') }}
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
        img: null,
        imgInput: null,
        file: null,
        imageFile: null,
        imageCropped: null,
        imageIsSelected: false,
        cropper: null,
        loadImage() {
            this.img = document.getElementById('image');
            if (this.cropper != null) {
                this.cropper.destroy();
            }

            this.cropper = new Cropper(this.img, {
                aspectRatio: 1 / 1,
                zoomable: false,
            });

            this.file = event.target.files[0]

            if (this.file.type.indexOf('image/') === -1) {
                alert('Bitte wähle eine Bild-Datei aus. / Please select an image file.')
                return
            }

            if (typeof FileReader === 'function') {
                const reader = new FileReader()

                reader.onload = (event) => {
                    this.imageFile = event.target?.result
                    this.cropper.replace(event.target?.result)
                };

                reader.readAsDataURL(this.file);
                this.imageIsSelected = true;
            } else {
                alert('Dein Browser scheint die FileReader-API nicht zu unterstützen. / Your browser does not seem to support the FileReader API.')
            }
        },
        cropPicture() {
            $wire.picture = this.cropper.getCroppedCanvas().toDataURL('image/jpeg')
            $wire.savePicture()
        },
        cancelPicture() {
            this.img = null
            this.imgInput = null
            this.file = null
            this.imageFile = null
            this.imageCropped = null
            this.cropper.destroy()
            this.imageIsSelected = false
        },
    }
})
</script>
@endscript