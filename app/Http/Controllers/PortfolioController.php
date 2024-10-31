<?php

namespace App\Http\Controllers;

use App\Models\Holding;
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
    public function show(Request $request, Portfolio $portfolio)
    {
        if ($request->user()->cannot('readOnly', $portfolio)) {
            abort(403);
        }

        $portfolio->load(['transactions', 'holdings']);
        
        // get portfolio metrics
        $metrics = cache()->remember(
            'portfolio-metrics-' . $portfolio->id, 
            60, 
            function () use ($portfolio) {
                return Holding::query()
                        ->portfolio($portfolio->id)
                        ->withPortfolioMetrics()
                        ->first();
            }
        );

        $formattedHoldings = $this->getFormattedHoldings($portfolio);
        
        return view('portfolio.show', compact(['portfolio', 'metrics', 'formattedHoldings']));
    }

    public function getFormattedHoldings($portfolio)
    {
        $formattedHoldings = '';
        foreach($portfolio->holdings as $holding) {
            $formattedHoldings .= " * Holding of ".$holding->market_data->name." (".$holding->symbol.")" 
                                    ."; with ". ($holding->quantity > 0 ? $holding->quantity : 'ZERO') . " shares"
                                    ."; avg cost basis ". $holding->average_cost_basis
                                    ."; curr market value ". $holding->market_data->market_value 
                                    ."; unrealized gains ". $holding->market_gain_dollars 
                                    ."; realized gains ". $holding->realized_gain_dollars
                                    ."; dividends earned ". $holding->dividends_earned
                                    ."\n\n";

        }
        return $formattedHoldings;
    }
}
