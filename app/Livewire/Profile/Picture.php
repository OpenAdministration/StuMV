<?php

namespace App\Livewire\Profile;

use App\Ldap\User;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
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
        if ($username == auth()->user()->username || auth()->user()->can('superadmin', User::class)) {
            $this->currentUsername = $username;
        } elseif ($username == auth()->user()->username) {
            $this->currentUsername = auth()->user()->username;
        } else {
            abort('403');
        }
    }
    
    public function render()
    {
        $user = User::findOrFailByUsername($this->currentUsername);
        $this->uid = $user->getFirstAttribute('uid');

        return view('livewire.profile.picture', [
            'jpegPhoto' => $user->getFirstAttribute('jpegPhoto'),
            'givenName' => $user->getFirstAttribute('givenName'),
            'sn' => $user->getFirstAttribute('sn'),
        ]);
    }

    public function savePicture()
    {
        $imgBase64 = str_replace('data:image/jpeg;base64,', '', $this->picture);
        $img = imagecreatefromstring(base64_decode($imgBase64)); // convert base64 string to image object
        $width = imagesx($img); // initial width of the image
        $height = imagesy($img); // initial height of the image
        $imgSize = 400; // size the image should be resized to

        if ($width > $imgSize || $height > $imgSize) {
            // Resize the image
            $thumb = imagecreatetruecolor($imgSize, $imgSize);
            imagecopyresized($thumb, $img, 0, 0, 0, 0, $imgSize, $imgSize, $width, $height);
            ob_start();
            imagejpeg($thumb, NULL);
            $img = ob_get_clean();
            $imgBase64 = base64_encode($img);
        }

        // Write image URL to LDAP
        $user = User::findOrFailByUsername($this->uid);
        $user->setAttribute('jpegPhoto', 'data:image/jpeg;base64,' . $imgBase64);
        $user->save();

        // Save image to storage
        Storage::put('avatars/' . $currentUsername . '.jpg', $img);

        return redirect()->route('profile.picture', ['username' => $this->uid])->with('message', __('Saved'));
    }

    public function deletePicture()
    {
        // Remove image URL from LDAP
        $user = User::findOrFailByUsername($this->uid);
        $user->removeAttribute('jpegPhoto');
        $user->save();

        return redirect()->route('profile.picture', ['username' => $this->uid])->with('message', __('ImageRemoved'));
    }
}
