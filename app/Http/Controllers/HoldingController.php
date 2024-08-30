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

        $holding = Holding::with([
                            'market_data',
                            'transactions' => function ($query) use ($symbol) {
                                $query->where('transactions.symbol', $symbol);
                            }
                        ])
                        ->symbol($symbol)
                        ->portfolio($portfolio->id)
                        ->firstOrFail();

        return view('holding.show', compact(['portfolio', 'holding']));
    }
}
