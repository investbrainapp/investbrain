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

        $holding = Holding::where([
            'portfolio_id' => $portfolio->id,
            'symbol' => $symbol
        ])->firstOrFail();

        $market_data = $holding->market_data;

        return view('holding.show', compact(['portfolio', 'holding', 'market_data']));
    }
}
