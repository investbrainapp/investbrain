<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
        Gate::authorize('readOnly', $portfolio);

        $portfolio->load(['transactions', 'holdings']);

        // get portfolio metrics
        $metrics = cache()->tags(['metrics-'.$request->user()->id])->remember(
            'portfolio-metrics-'.$portfolio->id,
            60,
            function () use ($portfolio) {
                return Holding::query()
                    ->portfolio($portfolio->id)
                    ->getPortfolioMetrics();
            }
        );

        $formattedHoldings = $portfolio->getFormattedHoldings();

        return view('portfolio.show', compact(['portfolio', 'metrics', 'formattedHoldings']));
    }
}
