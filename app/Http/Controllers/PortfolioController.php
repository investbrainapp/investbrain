<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;

class PortfolioController extends Controller
{

    /**
     * Display form to create specified resource.
     *
     * @param  Portfolio  $portfolio
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('livewire.portfolio.create');
    }

    /**
     * Display the specified resource.
     *
     * @param  Portfolio  $portfolio
     * @return \Illuminate\Http\Response
     */
    public function show(Portfolio $portfolio)
    {
        // $this->authorize('view', $portfolio);

        return view('livewire.portfolio.show', [
            'portfolio' => $portfolio
        ]);

        
    }
}
