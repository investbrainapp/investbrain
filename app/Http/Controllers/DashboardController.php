<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use stdClass;

class DashboardController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('portfolios');

        $dashboard = new stdClass;
        $dashboard->marketGainLoss = rand(-200, 3999);
        $dashboard->totalCostBasis = rand(-200, 3999);
        $dashboard->totalMarketValue = rand(-200, 3999);
        $dashboard->realizedGainLoss = rand(-200, 3999);
        $dashboard->dividendsEarned = rand(-200, 3999);

        return view('dashboard', compact('user', 'dashboard'));
    }
}
