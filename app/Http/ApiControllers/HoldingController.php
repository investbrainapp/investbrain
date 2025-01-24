<?php

namespace App\Http\ApiControllers;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\ApiControllers\Controller as ApiController;

class HoldingController extends ApiController
{
    public function me(Request $request)
    {
        return UserResource::make($request->user());
    }
}