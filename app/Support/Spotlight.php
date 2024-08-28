<?php

namespace App\Support;

use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Http\Request;
 
class Spotlight
{
    public function search(Request $request)
    {
        
        $results = collect();

        if (!$request->user()) {

            return $results;
        }

        $portfolios = $request->user()->portfolios()->where('title', 'LIKE', '%'.$request->input('search').'%')->limit(5)->get();
        $portfolios->each(function($portfolio) use ($results) {

            $results->push([
                'name' => 'Portfolio: '. $portfolio->title,
                'description' => null,
                'link' => route('portfolio.show', ['portfolio' => $portfolio->id]),
                'avatar' => null
            ]);
        });

        $holdings = $request->user()->holdings()->where('holdings.symbol', 'LIKE', '%'.$request->input('search').'%')->limit(5)->get();
        $holdings->each(function($holding) use ($results) {

            $results->push([
                'name' => 'Holding: '. $holding->symbol,
                'description' => $holding->portfolio->title,
                'link' => route('portfolio.show', ['portfolio' => $holding->portfolio->id]),
                'avatar' => null
            ]);
        });

        return $results;
    }
}