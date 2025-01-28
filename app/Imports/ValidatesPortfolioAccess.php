<?php

namespace App\Imports;

use App\Models\Portfolio;

trait ValidatesPortfolioAccess
{
    public function validatePortfolioAccess($collection)
    {

        $uniquePortfolios = $collection->unique('portfolio_id')->pluck('portfolio_id');
        $countPortfoliosWithAccess = Portfolio::fullAccess($this->backupImport->user_id)
            ->whereIn('id', $uniquePortfolios)
            ->count();

        if (
            $countPortfoliosWithAccess < $uniquePortfolios->count()
        ) {
            throw new \Exception(__('You do not have access to that portfolio.'));
        }
    }
}
