<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $chunkSize = 500;

        // Path to the CSV file
        $csvFilePath = realpath(__DIR__.'/market_data_seed.csv');

        // Open the file in read mode
        if (($handle = fopen($csvFilePath, 'r')) !== false) {

            $header = null;
            $rowCount = 0;

            while (($row = fgetcsv($handle, 0, ',')) !== false) {

                if (! $header) {

                    // header must be the first row
                    $header = $row;

                } else {

                    $data = array_combine($header, $row);

                    $meta_data = json_decode(base64_decode($data['meta_data']), true);
                    $meta_data['source'] = 'market_data_seeder';

                    $rows[] = [
                        'symbol' => $data['symbol'],
                        'name' => $data['name'],
                        'currency' => $data['currency'],
                        'meta_data' => json_encode($meta_data),
                    ];

                    $rowCount++;

                    if ($rowCount % $chunkSize == 0) {
                        $this->bulkInsert($rows);
                        $rows = [];
                    }
                }
            }

            // final clean up
            if (! empty($rows)) {

                $this->bulkInsert($rows);
                $rows = [];
            }

            // Close the CSV file
            fclose($handle);

            echo "\n  > Imported $rowCount market data items successfully!";

        } else {

            echo "Failed to open the CSV.\n";
        }
    }

    private function bulkInsert($rows): void
    {
        try {

            DB::table('market_data')->upsert($rows, ['symbol'], ['name', 'currency', 'meta_data']);

        } catch (\Throwable $e) {

            throw new \Exception('Error: '.$e->getMessage());
        }

        gc_collect_cycles();
    }
}
