<?php

declare(strict_types=1);

namespace App\Actions\Holding;

use App\Models\Holding;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateHolding
{
    /**
     * Validate, authorize, and update a holding. Shared by the API controller and the
     * update-holding MCP tool so both enforce identical rules.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Holding $holding, array $data): Holding
    {
        $validated = Validator::make($data, $this->rules())->validate();

        Gate::authorize('fullAccess', $holding->portfolio);

        $holding->update($validated);

        return $holding;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reinvest_dividends' => ['sometimes', 'boolean'],
        ];
    }
}
