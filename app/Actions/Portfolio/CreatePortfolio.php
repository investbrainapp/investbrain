<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Validator;

class CreatePortfolio
{
    /**
     * Validate and create a portfolio for the authenticated user. Shared by the API
     * controller and the create-portfolio MCP tool so both enforce identical rules.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Portfolio
    {
        $validated = Validator::make($data, $this->rules())->validate();

        return Portfolio::create($validated);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'wishlist' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
