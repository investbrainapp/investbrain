<?php

namespace App\Livewire;

use App\Models\Holding;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Columns\CountColumn;

class UsersTable extends DataTableComponent
{
    public function builder(): Builder
    {
        
        return Holding::query()
            ->portfolio('9cf8a691-58ea-4bf9-99a9-399069bcb186')
            ->withCount(['transactions as num_transactions' => function ($query) {
                return $query->whereRaw('transactions.symbol = holdings.symbol');
            }]);
    }

    public function configure(): void
    {
        $this->setPaginationDisabled();
        $this->setSearchDisabled();
        $this->setColumnSelectDisabled();

        $this->setTableWrapperAttributes([
            'default' => false, 
            'default-styling' => false,
            'default-colors' => false,
        ]); 
        $this->setTableAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
            'class' => 'table',
        ]);
        $this->setTableAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
        ]);
        $this->setTheadAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
        ]);
        $this->setThAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false
        ]);
        $this->setThSortButtonAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false,
            'class' => 'text-secondary'
        ]);
        $this->setTbodyAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
        ]);
        $this->setTrAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false
        ]);
        $this->setTdAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false
        ]);

        $this->setFooterDisabled();
        $this->setDisplayPaginationDetailsDisabled();
        
        $this->setPrimaryKey('id');

    }

    public function columns(): array
    {
        return [
            Column::make("Symbol", "symbol")
                ->sortable(),
            Column::make("Number of Transactions")
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('num_transactions', $direction))
                ->label(fn ($row, Column $column) => $row->num_transactions),
            Column::make("Created at", "created_at")
                ->sortable(),
            Column::make("Updated at", "updated_at")
                ->sortable(),
        ];
    }
}
