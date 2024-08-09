<?php

namespace App\Support;

use App\Models\Portfolio;
use Illuminate\Http\Request;
 
class Spotlight
{
    public function search(Request $request)
    {
        
        if (!$request->user()) {
            return collect();
        }

        $portfolios = Portfolio::where('title', 'LIKE', '%'.$request->input('search').'%')->limit(5)->get();

        return $portfolios->map(function($portfolio){

            return [
                'name' => $portfolio->title,
                'description' => null,
                'link' => route('portfolio.show', ['portfolio' => $portfolio->id]),
                'avatar' => null
            ];
        });
    }
}