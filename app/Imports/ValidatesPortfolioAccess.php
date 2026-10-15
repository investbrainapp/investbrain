<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Portfolio;
use Illuminate\Support\Collection;

trait ValidatesPortfolioAccess
{
    /**
     * Ensure the importing user has full access to every given portfolio.
     *
     * @param  Collection<int, string>  $portfolioIds
     */
    public function validatePortfolioAccess(Collection $portfolioIds): void
    {
        $importingPortfolios = $portfolioIds->unique()->values();

        if ($importingPortfolios->isEmpty()) {
            return;
        }

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
