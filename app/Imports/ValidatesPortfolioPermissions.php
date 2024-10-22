<?php

namespace App\Imports;

use Exception;
use App\Models\Portfolio;

trait ValidatesPortfolioPermissions {
    
    public function validatePortfolioPermissions($collection)
    {
        $portfolios = auth()->user()->portfolios->pluck('id');
        
        $collection->pluck('portfolio_id')->unique()->each(function($portfolio) use ($portfolios) {

            if (
                !$portfolios->contains($portfolio)
                || auth()->user()->cannot('fullAccess', Portfolio::find($portfolio))
            ) {
    
                throw new Exception('You do not have permission to access that portfolio.');
            }
        });
    }
}
