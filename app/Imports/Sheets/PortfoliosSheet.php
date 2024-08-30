<?php

namespace App\Imports\Sheets;

use App\Models\Portfolio;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PortfoliosSheet implements ToCollection, WithValidation, WithHeadingRow, SkipsEmptyRows
{
    public function collection(Collection $portfolios)
    {
        Portfolio::withoutEvents(function () use ($portfolios) {
                
            foreach ($portfolios as $portfolio) {

                auth()->user()->portfolios()
                            ->where(['id' => $portfolio['portfolio_id']])
                            ->orWhere(['title' => $portfolio['title']])
                            ->firstOr(function () use ($portfolio) {

                                return Portfolio::make()->forceFill([
                                    'id' => $portfolio['portfolio_id'] ?? null,
                                    'title' => $portfolio['title'],
                                    'wishlist' => $portfolio['wishlist'] ?? false,
                                    'notes' => $portfolio['notes'],
                                ])->save();
                            });
            }
        });
    }

    public function rules(): array
    {
        return [
            'portfolio_id' => ['sometimes', 'nullable'],
            'title' => ['required', 'string'],
            'wishlist' => ['sometimes', 'nullable', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
