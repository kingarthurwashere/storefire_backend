<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Services\UserService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {
    }

    public function __invoke()
    {
        return response()->json(request()->user());
    }


    public function login(LoginUserRequest $req)
    {
        return $this->userService->login($req);
    }

    public function register(RegisterUserRequest $req,)
    {
        try {
            return $this->userService->register($req);
        } catch (\Throwable $th) {
            return response()->error($th->getMessage());
        }
    }

    public function logout(Request $req)
    {
        return $this->userService->logout($req);
    }
}
