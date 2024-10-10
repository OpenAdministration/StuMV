<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Illuminate\View\View;
use LdapRecord\Models\OpenLDAP\Entry;

class AppLayout extends Component
{
    public array $routeParams;

    public function __construct(){
        // Maybe there is a more Laravel way to make this work ...
        $params = Route::current()?->parameters();
        foreach ($params as $name => $entry){
            if($entry instanceof Entry){
                $params[$name] = $entry->getFirstAttribute($entry->getRouteKeyName());
            }
        }
        $this->routeParams = $params;
    }

    /**
     * Get the view / contents that represents the component.
     *
     * @return View
     */
    public function render(): View
    {
        return view('layouts.app', [
            'title' => 'StuMV',
            'routeParams' => $this->routeParams,
        ]);
    }



}
