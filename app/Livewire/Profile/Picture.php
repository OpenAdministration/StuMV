<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
use App\Ldap\User;
use App\Models\ProfilePicture;
use Flux\Flux;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Picture extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $realm_uid;

    #[Locked]
    public string $currentUsername;

    /** @var TemporaryUploadedFile|null */
    #[Validate(['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp', 'max:5120'])]
    public $upload = null;

    // Cropper.js's own crop-box selection, in the original image's pixel
    // coordinates (Cropper::getData()) - cropping happens server-side on the
    // originally uploaded file rather than re-uploading a client-cropped
    // canvas export, so there's only one image transfer and no extra
    // re-compression pass.
    public int $cropX = 0;

    public int $cropY = 0;

    public int $cropWidth = 0;

    public int $cropHeight = 0;

    public function mount(Community $realm, string $username): void
    {
        $this->authorize('manageProfile', [User::class, $realm, $username]);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;
    }

    protected function findLdapUser(string $username): User
    {
        return User::query()->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())->where('uid', '=', $username)->first() ?? abort(404);
    }

    public function render(): Factory|View
    {
        $user = $this->findLdapUser($this->currentUsername);
        $pictureDB = ProfilePicture::where('user', $this->currentUsername)->where('realm', $this->realm_uid)->first();
        $avatarID = null;
        if ($pictureDB) {
            $avatarID = $pictureDB->file_id;
        }

        return view('livewire.profile.picture', [
            'avatarID' => $avatarID,
            'givenName' => $user->getFirstAttribute('givenName'),
            'sn' => $user->getFirstAttribute('sn'),
        ]);
    }

    public function cancelUpload(): void
    {
        $this->reset('upload');
    }

    public function savePicture(): RedirectResponse
    {
        $this->validateOnly('upload');
        $this->validate(['cropWidth' => 'integer|min:1', 'cropHeight' => 'integer|min:1']);
        $upload = $this->upload;
        abort_unless($upload instanceof TemporaryUploadedFile, 422);
        $width = max(1, $this->cropWidth);
        $height = max(1, $this->cropHeight);

        // Cropped independently for each output - toBytes()/toBase64()
        // consume and reset an Image's pipeline, so producing two different
        // final encodings from one instance would silently re-process
        // already-encoded bytes instead of the original pixel data.
        $cropped = fn () => Image::fromPath($upload->getRealPath())
            ->crop($width, $height, $this->cropX, $this->cropY)
            ->resize(400, 400);

        // jpegPhoto is LDAP's own conventional attribute name - it expects
        // an actual JPEG, regardless of what format was uploaded.
        $user = $this->findLdapUser($this->currentUsername);
        $user->setAttribute('jpegPhoto', $cropped()->toJpg()->toBase64());
        $user->save();

        // Generate unique image ID
        $imgID = Str::uuid();

        // Local storage uses WebP - smaller than JPEG at equivalent quality,
        // same format realm branding images are stored in.
        Storage::disk('public')->put(
            'avatars/'.$imgID.'.webp',
            $cropped()->toWebp()->toBytes(),
        );

        // Save user image relation
        $pictureDB = ProfilePicture::updateOrCreate(
            ['user' => $this->currentUsername, 'realm' => $this->realm_uid],
            ['file_id' => $imgID],
        );
        $user->recordAction('profile.picture_changed', $this->realm_uid);

        $this->reset('upload');

        Flux::toast(variant: 'success', text: trans('profile.picture_added'));

        return to_route('profile.picture', ['realm' => $this->realm_uid, 'username' => $this->currentUsername]);
    }

    public function deletePicture(): RedirectResponse
    {
        // Remove image URL from LDAP
        $user = $this->findLdapUser($this->currentUsername);
        if ($user->hasAttribute('jpegPhoto')) {
            $user->removeAttribute('jpegPhoto');
            $user->save();
        }

        // Get user image relation from database
        $pictureDB = ProfilePicture::where('user', $this->currentUsername)->where('realm', $this->realm_uid)->first();

        if ($pictureDB !== null) {
            Storage::disk('public')->delete('avatars/'.$pictureDB->file_id.'.webp');
            $pictureDB->delete();
        }
        $user->recordAction('profile.picture_removed', $this->realm_uid);

        Flux::toast(variant: 'success', text: trans('profile.picture_removed'));

        return to_route('profile.picture', ['realm' => $this->realm_uid, 'username' => $this->currentUsername]);
    }
}
