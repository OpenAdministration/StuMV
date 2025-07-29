<div>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('Profile') }}</h1>
        </div>
    </div>
    <x-livewire-form :abort_route="null" wire:submit="save">
        <div class="mt-6 sm:mt-5 grid lg:grid-cols-2 gap-4">
            <x-input.group disabled :label="__('Username')" wire:model="uid">
                <x-slot:help>{{ __('validation.disabled', ['attribute' => 'username']) }}</x-slot:help>
            </x-input.group>
            <x-input.group disabled type="email" :label="__('E-Mail')" wire:model.live="email">
                <x-slot:help>{{ __('validation.disabled', ['attribute' => 'email']) }}</x-slot:help>
            </x-input.group>
        </div>
        <div class="grid lg:grid-cols-2 gap-4 mt-4">
            <x-input.group :label="__('Vorname')" wire:model.live="givenName"/>
            <x-input.group :label="__('Nachname')" wire:model.live="sn"/>
        </div>
        <div class="grid lg:grid-cols-2 gap-4 mt-4">
            <x-input.group :label="__('Studiengang')" wire:model.live="course"/>
        </div>
        <div class="grid lg:grid-cols-2 gap-4 mt-4">
            <x-input.group :label="__('Straße und Hausnummer')" wire:model.live="street"/>
            <div class="grid grid-cols-[1fr_2fr] gap-4">
                <x-input.group :label="__('Postleitzahl')" wire:model.live="postalCode"/>
                <x-input.group :label="__('Ort')" wire:model.live="city"/>
            </div>
        </div>
        <div class="grid lg:grid-cols-2 gap-4 mt-4">
            <x-input.group :label="__('Telefon')" wire:model.live="phone" />
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
                <button type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" wire:click="deletePicture">
                    {{ __('Entfernen') }}
                </button>
                @else
                <button type="button" class="text-sm font-semibold leading-6 text-gray-900" @click="cancelPicture">
                    {{ __('Abbrechen') }}
                </button>
                <button type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" @click="cropPicture">
                    {{ __('Speichern') }}
                </button>
                @endif
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