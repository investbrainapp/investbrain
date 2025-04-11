<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Portfolio;

trait ValidatesPortfolioAccess
{
    public function validatePortfolioAccess($collection)
    {

        $importingPortfolios = $collection->unique('portfolio_id')->pluck('portfolio_id');
        $portfoliosWithAccess = Portfolio::fullAccess($this->backupImport->user_id)
            ->whereIn('id', $importingPortfolios)
            ->count();

        if (
            $importingPortfolios->count() > $portfoliosWithAccess
        ) {
            throw new \Exception(__('You do not have access to that portfolio.'));
        }
    }
}
