<?php

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
