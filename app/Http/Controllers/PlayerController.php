<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PlayerController extends Controller
{
    public function show(): View
    {
        return view('player.popup');
    }
}
