<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Traits\HasLocalizedMarkdown;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class TermsOfServiceController extends Controller
{
    use HasLocalizedMarkdown;

    /**
     * Show the terms of service for the application.
     *
     * @return \Illuminate\View\View
     */
    public function show(Request $request)
    {
        $termsFile = $this->localizedMarkdownPath('terms.md');

        return view('terms', [
            'terms' => Str::markdown(file_get_contents($termsFile)),
        ]);
    }
}
