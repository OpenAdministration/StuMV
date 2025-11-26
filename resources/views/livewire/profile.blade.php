<div>
    <x-navbar-profile :username="$currentUsername" />

    <div class="mt-12 space-y-8">
        <x-livewire-form :abort_route="null" wire:submit="save">
            <div class="sm:mt-5 grid lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Username') }}</flux:label>
                    <flux:input wire:model="uid" disabled />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('E-Mail') }}</flux:label>
                    <flux:input wire:model="email" disabled />
                </flux:field>
            </div>
            <div class="grid lg:grid-cols-2 gap-6 mt-6">
                <flux:field>
                    <flux:label>{{ __('Vorname') }}</flux:label>
                    <flux:input wire:model="givenName" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Nachname') }}</flux:label>
                    <flux:input wire:model="sn" />
                </flux:field>
            </div>
            <div class="grid lg:grid-cols-2 gap-6 mt-6">
                <flux:field>
                    <flux:label>{{ __('Studiengang') }}</flux:label>
                    <flux:input wire:model="course" />
                </flux:field>
            </div>
            <div class="grid lg:grid-cols-2 gap-6 mt-6">
                <flux:field>
                    <flux:label>{{ __('Straße und Hausnummer') }}</flux:label>
                    <flux:input wire:model="street" />
                </flux:field>
                <div class="grid grid-cols-[1fr_2fr] gap-6">
                    <flux:field>
                        <flux:label>{{ __('Postleitzahl') }}</flux:label>
                        <flux:input wire:model="postalCode" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Ort') }}</flux:label>
                        <flux:input wire:model="city" />
                    </flux:field>
                </div>
            </div>
            <div class="grid lg:grid-cols-2 gap-6 mt-6">
                <flux:field>
                    <flux:label>{{ __('Telefon') }}</flux:label>
                    <flux:input wire:model="phone" />
                </flux:field>
            </div>
            <x-slot:abort_route>
                {{ url()->previous() }}
            </x-slot:abort_route>
        </x-livewire-form>

        <div class="sm:flex sm:items-center mt-6">
            <div class="sm:flex-auto">
                <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('Picture') }}</h1>
            </div>
        </div>
        <div class="mt-8 sm:mt-5">
            <div x-data="cropper">
                <div>
                    @if ($pictureUrl)
                    <img class="h-[15rem] rounded-md shadow-sm border border-zinc-200" src="{{ $pictureUrl }}" alt="Profile picture of {{ $givenName }} {{ $sn }}">
                    @else
                    <flux:file-upload wire:model="photos" multiple label="Upload files">
                        <flux:file-upload.dropzone
                            heading="Drop files here or click to browse"
                            text="JPG, PNG, GIF up to 10MB"
                        />
                    </flux:file-upload>
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
                    @if ($pictureUrl)
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