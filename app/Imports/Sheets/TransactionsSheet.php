<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Imports\ReadsSheetRows;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TransactionsSheet implements SkipsEmptyRows, SkipsUnknownSheets, ToArray, WithCalculatedFormulas, WithHeadingRow, WithValidation
{
    use ReadsSheetRows;

    /**
     * @var list<string>|null
     */
    protected ?array $currencies = null;

    public function title(): string
    {
        return 'Transactions';
    }

    /**
     * @return array<string, array{label: string, required: bool}>
     */
    public static function columns(): array
    {
        return [
            'transaction_id' => [
                'label' => 'Transaction ID',
                'required' => false,
            ],
            'symbol' => [
                'label' => 'Symbol',
                'required' => true,
            ],
            'portfolio_id' => [
                'label' => 'Portfolio ID',
                'required' => true,
            ],
            'transaction_type' => [
                'label' => 'Transaction Type',
                'required' => true,
            ],
            'quantity' => [
                'label' => 'Quantity',
                'required' => true,
            ],
            'cost_basis' => [
                'label' => 'Cost Basis',
                'required' => false,
            ],
            'sale_price' => [
                'label' => 'Sale Price',
                'required' => false,
            ],
            'currency' => [
                'label' => 'Currency',
                'required' => false,
            ],
            'split' => [
                'label' => 'Split',
                'required' => false,
            ],
            'reinvested_dividend' => [
                'label' => 'Reinvested Dividend',
                'required' => false,
            ],
            'date' => [
                'label' => 'Date',
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
        return [
            'transaction_id' => $this->string($row['transaction_id'] ?? null),
            'symbol' => $this->upper($row['symbol'] ?? null),
            'portfolio_id' => $this->string($row['portfolio_id'] ?? null),
            'transaction_type' => $this->string($row['transaction_type'] ?? null),
            'quantity' => $this->text($row['quantity'] ?? null),
            'cost_basis' => $this->text($row['cost_basis'] ?? null),
            'sale_price' => $this->text($row['sale_price'] ?? null),
            'currency' => $this->string($row['currency'] ?? null),
            'split' => $this->boolean($row['split'] ?? null),
            'reinvested_dividend' => $this->boolean($row['reinvested_dividend'] ?? null),
            'date' => $this->date($row['date'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function rowRules(array $row): array
    {
        return [
            'transaction_id' => ['nullable', 'uuid'],
            'symbol' => ['required', 'string', 'max:25', 'regex:/^[A-Za-z0-9.\-^=:\/_]+$/'],
            'portfolio_id' => ['required', 'uuid'],
            'transaction_type' => ['required', 'in:BUY,SELL'],
            // splits are recorded as a share adjustment, which is negative for reverse splits
            'quantity' => ['required', 'numeric', ($row['split'] ?? false) === true ? 'not_in:0' : 'gt:0'],
            'currency' => ['nullable', 'string', Rule::in($this->currencies())],
            'split' => ['boolean'],
            'reinvested_dividend' => ['boolean'],
            'cost_basis' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'symbol.regex' => __('This field may only contain letters, numbers and . - ^ = : / _ (no spaces).'),
            'transaction_type.in' => __('This field must be BUY or SELL.'),
            'currency.in' => __('This field must be a supported currency code, e.g. USD.'),
            'date.date' => __('This field is not a valid date. Try the YYYY-MM-DD format.'),
        ];
    }

    /**
     * Write the (validated and resolved) transactions in batches.
     */
    public function persist(): void
    {
        $transactions = collect($this->rows);

        if ($transactions->isEmpty()) {
            return;
        }

        $baseCurrency = config('investbrain.base_currency');

        // if has any transactions not in base currency, need to sync timeseries conversion rates
        if ($transactions->where('currency', '!=', $baseCurrency)->isNotEmpty()) {

            CurrencyRate::timeSeriesRates('', $transactions->min('date'), $transactions->max('date'));
        }

        $transactions->chunk($this->batchSize())->each(function (Collection $chunk) use ($baseCurrency) {

            // have to cast to native values
            $chunk = $chunk->map(function ($transaction) use ($baseCurrency) {

                // if transaction not in base currency, need to convert
                if ($transaction['currency'] == $baseCurrency) {
                    $cost_basis_base = $transaction['cost_basis'] ?? 0;
                    $sale_price_base = $transaction['sale_price'];
                } else {
                    $cost_basis_base = Currency::convert($transaction['cost_basis'], $transaction['currency'], date: $transaction['date']);
                    $sale_price_base = Currency::convert($transaction['sale_price'], $transaction['currency'], date: $transaction['date']);
                }

                return [
                    'id' => $transaction['transaction_id'],
                    'symbol' => $transaction['symbol'],
                    'portfolio_id' => $transaction['portfolio_id'],
                    'transaction_type' => $transaction['transaction_type'],
                    'quantity' => $transaction['quantity'],
                    'cost_basis' => $transaction['cost_basis'] ?? 0,
                    'sale_price' => $transaction['sale_price'],
                    'cost_basis_base' => $cost_basis_base,
                    'sale_price_base' => $sale_price_base,
                    'split' => $transaction['split'] ? 1 : 0,
                    'reinvested_dividend' => $transaction['reinvested_dividend'] ? 1 : 0,
                    'date' => $transaction['date'],
                ];
            });

            Transaction::upsert(
                $chunk->values()->toArray(),
                ['id'],
                [
                    'id',
                    'symbol',
                    'portfolio_id',
                    'transaction_type',
                    'quantity',
                    'cost_basis',
                    'sale_price',
                    'cost_basis_base',
                    'sale_price_base',
                    'split',
                    'reinvested_dividend',
                    'date',
                ]
            );
        });
    }

    public function batchSize(): int
    {
        return 500;
    }

    /**
     * @return list<string>
     */
    protected function currencies(): array
    {
        return $this->currencies ??= Currency::list()->pluck('currency')->all();
    }
}
