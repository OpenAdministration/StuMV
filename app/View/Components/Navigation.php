<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class Navigation extends Component
{
    public string $uid = '';

    public function __construct()
    {
        $community = Route::current()?->parameter('uid');
        if($community){
            $this->uid = $community->getFirstAttribute('ou');
        }
    }

    public function render()
    {
        return $this->view('components.navigation', ['uid' => $this->uid]);
    }
}
