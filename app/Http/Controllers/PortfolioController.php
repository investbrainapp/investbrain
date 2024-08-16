<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;

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

        $portfolio->marketGainLoss = rand(-200, 3999);
        $portfolio->totalCostBasis = rand(-200, 3999);
        $portfolio->totalMarketValue = rand(-200, 3999);
        $portfolio->realizedGainLoss = rand(-200, 3999);
        $portfolio->dividendsEarned = rand(-200, 3999);

        return view('portfolio.show', compact(['portfolio']));
    }
}
