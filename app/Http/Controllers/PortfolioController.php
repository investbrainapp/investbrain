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
        $metrics = cache()->tags(['metrics', 'portfolio', $portfolio->id])->remember(
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
