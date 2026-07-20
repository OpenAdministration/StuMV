<?php

namespace App\View\Components;

use App\Models\RealmBranding;
use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    public function __construct(public ?RealmBranding $branding = null) {}

    /**
     * Get the view / contents that represents the component.
     *
     * @return View
     */
    public function render()
    {
        return view('layouts.guest');
    }
}
