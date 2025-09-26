<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiTokenController extends Controller
{
    /**
     * Show the user API token screen.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('api.index', [
            'request' => $request,
            'user' => $request->user(),
        ]);
    }
}
