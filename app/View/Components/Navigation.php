<?php

namespace App\View\Components;

use App\Ldap\Community;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class Navigation extends Component
{
    public string $realm = '';

    public function __construct()
    {
        $community = Route::current()?->parameter('realm');
        // Still the raw route segment (a string), not yet resolved to a
        // Community, when the "realm" binding itself is what failed - e.g.
        // rendering the 404 for a URL with a nonexistent realm slug.
        if ($community instanceof Community) {
            $this->realm = $community->getFirstAttribute('ou');
        }
    }

    public function render()
    {
        return $this->view('components.navigation', ['realm' => $this->realm]);
    }
}
