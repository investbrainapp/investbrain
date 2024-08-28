<?php

namespace App\Imports\Sheets;

use App\Models\Portfolio;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PortfoliosSheet implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    // use Importable;

    public function collection(Collection $portfolios)
    {
        foreach ($portfolios->sortBy('date') as $portfolio) {

            auth()->user()->portfolios()
                        ->where(['id' => $portfolio['id']])
                        ->orWhere(['title' => $portfolio['title']])
                        ->firstOr(function () use ($portfolio) {

                            return Portfolio::make()->forceFill([
                                'id' => $portfolio['id'] ?? null,
                                'title' => $portfolio['title'],
                                'wishlist' => $portfolio['wishlist'] ?? false,
                                'notes' => $portfolio['notes'],
                            ])->save();
                        });
        }
    }
}
