<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\Request;

class HoldingController extends Controller
{

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Portfolio $portfolio, String $symbol)
    {

        $holding = $portfolio->holdings()
                        ->with(['market_data'])
                        ->symbol($symbol)
                        ->portfolio($portfolio->id)
                        ->first();

        return view('holding.show', compact(['portfolio', 'holding']));
    }
}
