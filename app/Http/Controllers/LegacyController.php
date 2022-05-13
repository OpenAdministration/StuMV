<?php

namespace App\Http\Controllers;

class LegacyController extends Controller
{
    public function index()
    {
        ob_start();
        include base_path() . "/legacy/web/index.php";
        $output = ob_get_clean();
    }
}
