<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    /**
     * Show the user connections screen.
     *
     * @return View
     */
    public function index(Request $request)
    {
        return view('connections.index', [
            'request' => $request,
            'user' => $request->user(),
        ]);
    }
}
