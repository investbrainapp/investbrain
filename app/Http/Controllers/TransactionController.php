<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\Transaction;

class TransactionController extends Controller
{

    /**
     * Display the specified resource.
     */
    public function index()
    {

        return view('transaction.index');
    }
}
