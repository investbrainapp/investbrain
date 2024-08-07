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
        foreach ($portfolios->sortBy('date') as $row) {

            Portfolio::myPortfolios()
                        ->where(['id' => $row['id']])
                        ->orWhere(['title' => $row['title']])
                        ->firstOr(function () use ($row) {

                            return Portfolio::make()->forceFill([
                                'id' => $row['id'] ?? null,
                                'title' => $row['title'],
                                'wishlist' => $row['wishlist'] ?? false,
                                'notes' => $row['notes'],
                            ])->save();
                        });
        }
    }
}
