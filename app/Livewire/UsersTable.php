<?php

namespace App\Livewire;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\User;

class UsersTable extends DataTableComponent
{
    protected $model = User::class;

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
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Name", "name")
                ->sortable(),
            Column::make("Email", "email")
                ->sortable(),
            Column::make("Created at", "created_at")
                ->sortable(),
            Column::make("Updated at", "updated_at")
                ->sortable(),
        ];
    }
}
