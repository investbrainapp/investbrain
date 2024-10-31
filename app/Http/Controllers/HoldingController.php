<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Http\Request;

class HoldingController extends Controller
{

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Portfolio $portfolio, String $symbol)
    {
        $holding = Holding::with([
                            'market_data',
                            'transactions' => function ($query) use ($symbol) {
                                $query->where('transactions.symbol', $symbol);
                            }
                        ])
                        ->symbol($symbol)
                        ->portfolio($portfolio->id)
                        ->firstOrFail();

        $formattedTransactions = $this->getFormattedTransactions($holding);

        return view('holding.show', compact(['portfolio', 'holding', 'formattedTransactions']));
    }

    public function getFormattedTransactions($holding)
    {
        $formattedTransactions = '';
        foreach($holding->transactions->where('symbol', $holding->symbol)->sortByDesc('date') as $transaction) {
            $formattedTransactions .= " * ".$transaction->date->format('Y-m-d') 
                                    ." ". $transaction->transaction_type 
                                    ." ". $transaction->quantity
                                    ." @ ". $transaction->cost_basis 
                                    ." each \n\n";
        }
        return $formattedTransactions;
    }
}
