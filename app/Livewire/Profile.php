<?php

namespace App\Livewire;

use App\Ldap\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Profile extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $email;

    #[Rule('string|required')]
    public string $givenName;

    #[Rule('string|required')]
    public string $sn;

    public $course;
    public $street;
    public $postalCode;
    public $city;
    public $phone;
    
    public $picture;
    public $pictureUrl;

    public $currentUsername;

    public function mount($username)
    {
        if ($username == auth()->user()->username || auth()->user()->can('superadmin', User::class)) {
            $this->currentUsername = $username;
        } elseif ($username == auth()->user()->username) {
            $this->currentUsername = auth()->user()->username;
        } else {
            abort('403');
        }
        $user = User::findOrFailByUsername($this->currentUsername);
        $this->uid = $user->getFirstAttribute('uid');
        $this->givenName = $user->getFirstAttribute('givenName');
        $this->sn = $user->getFirstAttribute('sn');
        $this->email = $user->getFirstAttribute('mail');
        $this->course = $user->getFirstAttribute('description');
        $this->street = $user->getFirstAttribute('street');
        $this->postalCode = $user->getFirstAttribute('postalCode');
        $this->city = $user->getFirstAttribute('l');
        $this->phone = $user->getFirstAttribute('telephoneNumber');
        $this->pictureUrl = $user->getFirstAttribute('jpegPhoto');
    }

    public function render()
    {
        return view('livewire.profile')->title(__('Profile'));
    }

    public function save()
    {
        $this->validate();
        $user = User::findOrFailByUsername($this->uid);
        $user->setAttribute('mail', $this->email);
        $user->setAttribute('givenName', $this->givenName);
        $user->setAttribute('sn', $this->sn);
        $user->setAttribute('cn', $this->givenName . ' ' . $this->sn);
        $user->setAttribute('description', $this->course);
        $user->setAttribute('street', $this->street);
        $user->setAttribute('postalCode', $this->postalCode);
        $user->setAttribute('l', $this->city);
        $user->setAttribute('telephoneNumber', $this->phone);
        $user->save();
        return redirect()->route('profile')->with('message', __('Saved'));
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
