<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
