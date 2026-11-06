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
        $user = $request->user()->load(['portfolios', 'holdings']);

        // the recent activity card only shows the latest five
        $recentTransactions = $user->transactions()
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.created_at')
            ->limit(5)
            ->get();

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

        return view('dashboard', compact('user', 'metrics', 'recentTransactions'));
    }
}
