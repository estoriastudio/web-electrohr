<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('index');
    }

    public function users()
    {
        return view('users.index');
    }

    public function settings()
    {
        return view('settings');
    }
}
