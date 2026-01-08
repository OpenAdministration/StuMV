<?php

namespace App\Livewire\Profile;

use App\Ldap\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Picture extends Component
{
    #[Locked]
    public ?string $currentUsername = null;

    #[Locked]
    public ?string $uid = null;

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

        return view('livewire.profile.picture');
    }

    public function savePicture()
    {
        $img = imagecreatefromstring(base64_decode(str_replace('data:image/jpeg;base64,', '', $this->picture))); // convert base64 string to image object
        $imgSize = 400; // size the image should be resized to
        $imgFileName = Str::uuid() . '.jpg'; // generate a file name
        $width = imagesx($img); // initial width of the image
        $height = imagesy($img); // initial height of the image

        // Resize the image
        $thumb = imagecreatetruecolor($imgSize, $imgSize);
        imagecopyresized($thumb, $img, 0, 0, 0, 0, $imgSize, $imgSize, $width, $height);
        ob_start();
        imagejpeg($thumb, NULL);
        $imgResized = ob_get_clean();

        // Put resized image into Laravel Storage
        Storage::disk('public')->put('pictures/' . $imgFileName, $imgResized, 'public');

        // Set picture URL
        $this->pictureUrl = Storage::disk('public')->url('pictures/' . $imgFileName);

        // Write image URL to LDAP
        $user = User::findOrFailByUsername($this->uid);
        $user->setAttribute('jpegPhoto', $this->pictureUrl);
        $user->save();

        return redirect()->route('profile')->with('message', __('Saved'));
    }

    public function deletePicture()
    {
        // Remove image from Laravel Storage
        Storage::disk('public')->delete(str_replace(config('app.url') . '/storage/', '', $this->pictureUrl));

        // Remove image URL from LDAP
        $user = User::findOrFailByUsername($this->uid);
        $user->removeAttribute('jpegPhoto');
        $user->save();

        return redirect()->route('profile')->with('message', __('ImageRemoved'));
    }
}
