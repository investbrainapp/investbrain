<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Interfaces\MarketSentiment\MarketSentimentInterface;
use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

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

        $marketSentiment = new Collection;

        if (filled(config('investbrain.sentiment_provider'))) {
            try {
                $marketSentiment = app(MarketSentimentInterface::class)->sentiment($holding->symbol);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $formattedTransactions = $holding->getFormattedTransactions();

        return view('holding.show', compact(['portfolio', 'holding', 'formattedTransactions', 'marketSentiment']));
    }
}
