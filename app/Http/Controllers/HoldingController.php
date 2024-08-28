<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Http\Request;

class HoldingController extends Controller
{

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Portfolio $portfolio, String $symbol)
    {


        $holding = Holding::query()
                            ->portfolio($portfolio->id)
                            ->symbol($symbol)
                            ->first();

        $market_data = $holding->market_data;

        return view('holding.show', compact(['portfolio', 'holding', 'market_data']));
    }
}
