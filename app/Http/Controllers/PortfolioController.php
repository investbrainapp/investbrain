<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\DailyChange;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Requests\PortfolioRequest;
use Asantibanez\LivewireCharts\Models\LineChartModel;
use Livewire\Livewire;
use Livewire\Volt\Volt;

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
