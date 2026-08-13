<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdatePortfolio
{
    /**
     * Validate, authorize, and update a portfolio. Shared by the API controller and the
     * update-portfolio MCP tool so both enforce identical rules.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Portfolio $portfolio, array $data): Portfolio
    {
        $validated = Validator::make($data, $this->rules())->validate();

        Gate::authorize('fullAccess', $portfolio);

        $portfolio->update($validated);

        return $portfolio;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:5', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'wishlist' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
