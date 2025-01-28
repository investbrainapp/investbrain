<?php

namespace App\Http\ApiControllers;

use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function me(Request $request)
    {
        return UserResource::make($request->user());
    }
}
