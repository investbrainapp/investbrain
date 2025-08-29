<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Http\Request;

class HoldingController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(Request $request, Portfolio $portfolio, string $symbol)
    {
        $holding = Holding::with([
            'market_data',
            'transactions' => function ($query) use ($symbol) {
                $query->where('transactions.symbol', $symbol);
            },
        ])
            ->symbol($symbol)
            ->portfolio($portfolio->id)
            ->firstOrFail();

        $formattedTransactions = $holding->getFormattedTransactions();

        return view('holding.show', compact(['portfolio', 'holding', 'formattedTransactions']));
    }
}
