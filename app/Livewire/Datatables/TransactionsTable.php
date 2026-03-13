<?php

declare(strict_types=1);

namespace App\Livewire\Datatables;

use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Illuminate\View\View;
use Livewire\Component;

class TransactionsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->with(['portfolio', 'market_data'])
                    ->myTransactions()
                    ->addSelect(['transactions.*'])
                    ->selectRaw('
                        (CASE
                            WHEN transaction_type = \'SELL\'
                            THEN COALESCE(transactions.sale_price, 0)
                            ELSE COALESCE((SELECT market_value FROM market_data WHERE market_data.symbol = transactions.symbol LIMIT 1), 0)
                        END) - COALESCE(transactions.cost_basis, 0) AS gain_dollars')
            )
            ->defaultSort('date', 'desc')
            ->extremePaginationLinks()
            ->paginated([15])
            ->defaultPaginationPageOption(15)
            ->recordUrl(fn ($record) => route('holding.show', ['portfolio' => $record->portfolio_id, 'symbol' => $record->symbol]))
            ->columns([
                TextColumn::make('date')
                    ->label(__('Date'))
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('M d, Y')),
                TextColumn::make('portfolio.title')
                    ->label(__('Portfolio'))
                    ->sortable(),
                TextColumn::make('symbol')
                    ->label(__('Symbol'))
                    ->sortable(),
                TextColumn::make('market_data.name')
                    ->label(__('Name'))
                    ->sortable(),
                TextColumn::make('transaction_type')
                    ->label(__('Type'))
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn ($state, $record) => view('components.ui.badge', [
                        'value' => $record->split ? 'SPLIT' : ($record->reinvested_dividend ? 'REINVEST' : $record->transaction_type),
                        'class' => ($record->transaction_type == 'BUY' ? 'badge-success' : 'badge-error').' badge-sm mr-3',
                    ])->render()),
                TextColumn::make('quantity')
                    ->label(__('Quantity'))
                    ->sortable(),
                TextColumn::make('cost_basis')
                    ->label(__('Cost Basis'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data->currency)),
                TextColumn::make('gain_dollars')
                    ->label(__('Gain/Loss'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data->currency)),
            ]);
    }

    public function render(): View
    {
        return view('livewire.datatables.transactions-table');
    }
}
