<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Imports\ImportSheet;
use App\Models\BackupImport;
use App\Models\Holding;

class ConfigSheet extends ImportSheet
{
    public function title(): string
    {
        return 'Config';
    }

    /**
     * @return array<string, array{label: string, required: bool}>
     */
    public static function columns(): array
    {
        return [
            'key' => [
                'label' => 'Key',
                'required' => true,
            ],
            'value' => [
                'label' => 'Value',
                'required' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalize(array $row): array
    {
        $value = $this->text($row['value'] ?? null);

        return [
            'key' => $this->string($row['key'] ?? null),
            'value' => is_scalar($value) ? (string) $value : $value,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function rowRules(array $row): array
    {
        return [
            'key' => ['required', 'string'],
            'value' => ['required', 'string'],
        ];
    }

    public function persist(BackupImport $backupImport): void
    {
        $user = $backupImport->user;

        foreach ($this->rows as $config) {

            switch ($config['key']) {
                case 'name':
                    $user->setAttribute('name', $config['value']);
                    $user->save();
                    break;

                case 'locale':
                    $user->setOption('locale', $config['value']);
                    $user->save();
                    break;

                case 'display_currency':
                    $user->setOption('display_currency', $config['value']);
                    $user->save();
                    break;

                case 'reinvested_dividends':
                    if (json_validate($config['value'])) {
                        foreach (json_decode($config['value'], true) as $reinvest) {
                            Holding::myHoldings($user->id)
                                ->where('portfolio_id', $reinvest['portfolio_id'] ?? null)
                                ->where('symbol', $reinvest['symbol'] ?? null)
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
}
