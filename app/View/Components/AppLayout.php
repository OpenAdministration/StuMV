<?php

namespace App\View\Components;

use Illuminate\View\Component;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('layouts.app', ['navigation' => $this->getNavigation()]);
    }

    /**
     * @return string[] route => [Name, Icon]
     */
    public function getNavigation(){
        return [
            'dashboard' => 'Realms',
        ];
    }
}
