<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\DailyChange;

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
        $portfolio->load(['transactions', 'holdings']);
        
        // get portfolio metrics
        $metrics = cache()->tags(['metrics', 'portfolio', auth()->user()->id, $portfolio->id])->remember(
            'portfolio-metrics-' . $portfolio->id, 
            60, 
            function () use ($portfolio) {
                return Holding::query()
                        ->portfolio($portfolio->id)
                        ->withPortfolioMetrics()
                        ->first();
            }
        );
        
        return view('portfolio.show', compact(['portfolio', 'metrics']));
    }
}
