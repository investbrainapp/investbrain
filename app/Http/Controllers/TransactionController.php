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
        $user = request()->user();

        return view('transaction.index', compact('user'));
    }
}
