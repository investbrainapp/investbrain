<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Gate;

class DeletePortfolio
{
    /**
     * Authorize and delete a portfolio. Shared by the API controller and the
     * delete-portfolio MCP tool so both enforce identical rules.
     */
    public function handle(Portfolio $portfolio): void
    {
        Gate::authorize('fullAccess', $portfolio);

        $portfolio->delete();
    }
}
