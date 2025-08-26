<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Models\BackupImport;
use App\Models\Holding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;

class ConfigSheet implements SkipsEmptyRows, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    public function __construct(
        public BackupImport $backupImport
    ) {}

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                DB::commit();
                $this->backupImport->update([
                    'message' => __('Importing configurations...'),
                ]);
                DB::beginTransaction();
            },
        ];
    }

    public function collection(Collection $configs)
    {
        foreach ($configs as $config) {

            switch ($config['key']) {
                case 'name':
                    $this->backupImport->user->setAttribute('name', $config['value']);
                    $this->backupImport->user->save();
                    break;

                case 'locale':
                    $this->backupImport->user->setOption('locale', $config['value']);
                    $this->backupImport->user->save();
                    break;

                case 'display_currency':
                    $this->backupImport->user->setOption('display_currency', $config['value']);
                    $this->backupImport->user->save();
                    break;

                case 'reinvested_dividends':
                    if (json_validate($config['value'])) {
                        foreach (json_decode($config['value'], true) as $reinvest) {
                            Holding::myHoldings($this->backupImport->user->id)
                                ->where('portfolio_id', $reinvest['portfolio_id'])
                                ->where('symbol', $reinvest['symbol'])
                                ->update([
                                    'reinvest_dividends' => true,
                                ]);
                        }
                    }
                    break;

                default:
                    break;
            }
        }
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string'],
            'value' => ['required', 'string'],
        ];
    }
}
