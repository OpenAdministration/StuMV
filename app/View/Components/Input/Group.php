<?php

namespace App\View\Components\Input;

use Illuminate\View\Component;

class Group extends Component
{
    public $id = '';

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.input.group');
    }
}
