<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Traits\HasLocalizedMarkdown;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class PrivacyPolicyController extends Controller
{
    use HasLocalizedMarkdown;

    /**
     * Show the privacy policy for the application.
     *
     * @return \Illuminate\View\View
     */
    public function show(Request $request)
    {
        $policyFile = $this->localizedMarkdownPath('policy.md');

        return view('policy', [
            'policy' => Str::markdown(file_get_contents($policyFile)),
        ]);
    }
}
