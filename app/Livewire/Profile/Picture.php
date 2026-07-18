<?php

namespace App\Livewire\Profile;

use App\Ldap\User;
use App\Models\ProfilePicture;
use Flux\Flux;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Picture extends Component
{
    #[Locked]
    public ?string $currentUsername = null;

    #[Locked]
    public ?string $uid = null;

    public $picture = null;

    public function mount($username)
    {
        $this->authorize('manageProfile', [User::class, $username]);
        $this->currentUsername = $username;
    }

    public function render()
    {
        $user = User::findOrFailByUsername($this->currentUsername);
        $this->uid = $user->getFirstAttribute('uid');
        $pictureDB = ProfilePicture::where('user', $this->currentUsername)->first();
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

    public function savePicture()
    {
        $img = Image::fromBase64(Str::after($this->picture, 'base64,'));
        $imgResized = $img->resize(400, 400);
        $imgBase64 = $imgResized->toBase64();

        // Write base64 encoded image to LDAP
        $user = User::findOrFailByUsername($this->uid);
        $user->setAttribute('jpegPhoto', $imgBase64);
        $user->save();

        // Generate unique image ID
        $imgID = Str::uuid();

        // Save image to storage
        Storage::disk('public')->put('avatars/'.$imgID.'.jpg', $imgResized);

        // Save user image relation
        $pictureDB = ProfilePicture::create([
            'user' => $this->currentUsername,
            'file_id' => $imgID,
        ]);

        Flux::toast(variant: 'success', text: trans('profile.picture_added'));

        return to_route('profile.picture', ['username' => $this->uid]);
    }

    public function deletePicture()
    {
        // Remove image URL from LDAP
        $user = User::findOrFailByUsername($this->uid);
        if ($user->hasAttribute('jpegPhoto')) {
            $user->removeAttribute('jpegPhoto');
            $user->save();
        }

        // Get user image relation from database
        $pictureDB = ProfilePicture::where('user', $this->currentUsername)->first();

        // Remove image from storage
        Storage::disk('public')->delete('avatars/'.$pictureDB->file_id.'.jpg');

        // Delete database entry
        $pictureDB->delete();

        Flux::toast(variant: 'success', text: trans('profile.picture_removed'));

        return to_route('profile.picture', ['username' => $this->uid]);
    }
}
