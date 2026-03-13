<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\Holding;
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
use Livewire\Component;

class HoldingsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public $portfolio;

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function table(Table $table): Table
    {

        return $table
            ->query(
                Holding::query()
                    ->portfolio($this->portfolio->id)
                    ->withMarketData()
                    ->withCount(['transactions as num_transactions' => function ($query) {
                        return $query->whereRaw('transactions.symbol = holdings.symbol');
                    }])
                    ->withPerformance()
            )
            ->defaultSort('symbol', 'asc')
            ->paginated(false)
            ->recordUrl(fn ($record) => route('holding.show', ['portfolio' => $record->portfolio_id, 'symbol' => $record->symbol]))
            ->columns([
                TextColumn::make('symbol')
                    ->label(__('Symbol'))
                    ->sortable(),
                TextColumn::make('market_data.name')
                    ->label(__('Name'))
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label(__('Quantity'))
                    ->sortable(),
                TextColumn::make('average_cost_basis')
                    ->label(__('Average Cost Basis'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data?->currency)),
                TextColumn::make('total_cost_basis')
                    ->label(__('Total Cost Basis'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data?->currency)),
                TextColumn::make('market_data.market_value')
                    ->label(__('Market Value'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data?->currency)),
                TextColumn::make('total_market_value')
                    ->label(__('Total Market Value'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data?->currency)),
                TextColumn::make('market_gain_dollars')
                    ->label(__('Market Gain/Loss'))
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data?->currency).view('components.ui.gain-loss-arrow-badge', [
                        'costBasis' => $record->average_cost_basis,
                        'marketValue' => $record->market_data?->market_value,
                        'small' => true,
                    ])->render()),
                TextColumn::make('realized_gain_dollars')
                    ->label(__('Realized Gain/Loss'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data?->currency)),
                TextColumn::make('dividends_earned')
                    ->label(__('Dividends Earned'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data?->currency)),
                TextColumn::make('market_data.fifty_two_week_low')
                    ->label(__('52 week low'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data?->currency)),
                TextColumn::make('market_data.fifty_two_week_high')
                    ->label(__('52 week high'))
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => Number::currency($state ?? 0, $record->market_data?->currency)),
                TextColumn::make('num_transactions')
                    ->label(__('Number of Transactions'))
                    ->sortable(),
                TextColumn::make('market_data.updated_at')
                    ->label(__('Last Refreshed'))
                    ->sortable()
                    ->since(),
            ])
            ->stackedOnMobile();
    }

    public function render(): string
    {
        return <<<'HTML'
        <div>
            {{ $this->table }}
        </div>
        HTML;
    }
}
