<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

class Spotlight
{
    public function search(Request $request)
    {

        $results = collect();

        if (! $request->user()) {

            return $results;
        }

        $portfolios = $request->user()->portfolios()
            ->whereFullText('title', $request->input('search'))
            ->limit(5)
            ->get();
        $portfolios->each(function ($portfolio) use ($results) {

            $results->push([
                'name' => 'Portfolio: '.$portfolio->title,
                'description' => null,
                'link' => route('portfolio.show', ['portfolio' => $portfolio->id]),
                'avatar' => null,
            ]);
        });

        $holdings = $request->user()->holdings()
            ->where('holdings.quantity', '>', 0)
            ->where(function ($query) use ($request) {
                return $query->whereFullText('holdings.symbol', $request->input('search'))
                    ->orWhereFullText('market_data.name', $request->input('search'));
            })
            ->limit(5)
            ->get();
        $holdings->each(function ($holding) use ($results) {

            $results->push([
                'name' => 'Holding: '.$holding->market_data->name.' ('.$holding->symbol.')',
                'description' => $holding->portfolio->title,
                'link' => route('holding.show', ['portfolio' => $holding->portfolio->id, 'symbol' => $holding->symbol]),
                'avatar' => null,
            ]);
        });

        return $results;
    }
}
