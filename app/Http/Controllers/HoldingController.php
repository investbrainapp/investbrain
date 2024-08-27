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

        $holding = $request->user()
            ->holdings()
            ->where([
                'holdings.portfolio_id' => $portfolio->id,
                'holdings.symbol' => $symbol
            ])->firstOrFail();

        $market_data = $holding->market_data;

        return view('holding.show', compact(['portfolio', 'holding', 'market_data']));
    }
}
