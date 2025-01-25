<?php

namespace App\Http\ApiControllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use HackerEsq\FilterModels\FilterModels;
use App\Http\Resources\TransactionResource;
use App\Http\ApiControllers\Controller as ApiController;

class TransactionController extends ApiController
{
    public function index(FilterModels $filters)
    {

        $filters->setQuery(Transaction::query());
        $filters->setScopes(['myTransactions']);
        $filters->setSearchableColumns(['symbol']);

        return TransactionResource::collection($filters->paginated());
    }
}