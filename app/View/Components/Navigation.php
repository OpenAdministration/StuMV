<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class Navigation extends Component
{
    public string $realm = '';

    public function __construct()
    {
        $community = Route::current()?->parameter('realm');
        if ($community) {
            $this->realm = $community->getFirstAttribute('ou');
        }
    }

    public function render()
    {
        return $this->view('components.navigation', ['realm' => $this->realm]);
    }
}
