<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\Holding;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('portfolios');

        // get portfolio metrics
        $metrics = cache()->remember(
            'dashboard-metrics-' . $user->id, 
            10, 
            function () {
                return
                 Holding::query()
                    ->getPortfolioMetrics()
                    ->first();
            }
        );

        return view('dashboard', compact('user', 'metrics'));
    }
}
