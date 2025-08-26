<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Holding;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load(['portfolios', 'holdings', 'transactions']);

        // get portfolio metrics
        $metrics = cache()->tags(['metrics-'.$user->id])->remember(
            'dashboard-metrics-'.$user->id,
            10,
            function () {
                return Holding::query()
                    ->myHoldings()
                    ->withoutWishlists()
                    ->getPortfolioMetrics();
            }
        );

        return view('dashboard', compact('user', 'metrics'));
    }
}
