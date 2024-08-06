<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('portfolio.create');
    }

    /**
     * Display the specified resource.
     */
    public function show(Portfolio $portfolio)
    {
        return view('portfolio.show', compact('portfolio'));
    }

}
