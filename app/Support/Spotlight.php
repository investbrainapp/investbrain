<?php

namespace App\Support;

use Illuminate\Http\Request;
 
class Spotlight
{
    public function search(Request $request)
    {
        // Do your search logic here
        // IMPORTANT: apply any security concern here

        if (!auth()->user()) {
            return collect();
        }

        return collect([
            [
                'name' => 'Mary',                           // Any string
                'description' => 'Software Engineer',       // Any string
                'link' => '/users/1',                       // Any valid route
                'avatar' => 'http://...'                    // Any image url
            ]
        ]);
    }
}