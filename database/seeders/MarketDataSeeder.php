<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

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
        // $csvFilePath = storage_path('app/market_data_seed.csv');
        $csvFilePath = realpath(__DIR__.'/market_data_seed.csv');

        // Open the file in read mode
        if (($handle = fopen($csvFilePath, 'r')) !== false) {

            $header = null;
            $rows = [];
            $rowCount = 0;

            while (($row = fgetcsv($handle, 0, ',')) !== false) {

                if (!$header) {

                    // header must be the first row
                    $header = $row;

                } else {

                    try {
                        $data = array_combine($header, $row);

                        $rows[] = [
                            'symbol' => $data['symbol'], 
                            'name' => $data['name'], 
                            'meta_data' => json_encode([
                                'country' => $data['country'],
                                'first_trade_year' => $data['first_trade_year'],
                                'sector' => $data['sector'],
                                'industry' => $data['industry'],
                            ]), 
                        ];

                        $rowCount++;

                        if ($rowCount % $chunkSize == 0) {
                            DB::table('market_data')->insertOrIgnore($rows);
                            $rows = []; 
                        }
                    } catch (\Throwable $e) {
                        
                        throw new \Exception('Error: '. $e->getMessage());
                    }
                }
            }

            // final clean up
            if (!empty($rows)) {
                DB::table('market_data')->insertOrIgnore($rows);
            }

            // Close the CSV file
            fclose($handle);

            echo "Imported $rowCount market data items successfully!\n";

        } else {

            echo "Failed to open the CSV.\n";
        }
    }
}
