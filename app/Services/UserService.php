<?php

namespace App\Services;

use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserService
{

    public function register(RegisterUserRequest $req)
    {
        $user = User::create(array_merge($req->only('username', 'email', 'name', 'phone'), [
            'password' => bcrypt($req->input('password')),
            'email_verification_token' => Str::random(16)
        ]));

        return response()->success($user, 'User registered successfully', 201);
    }

    public function login(LoginUserRequest $login_user_request)
    {
        $credentials = $login_user_request->only('login', 'password');
        $login = $credentials['login'];

        // Attempt to authenticate using username, email, or phone
        $user = User::where('username', $login)
            ->orWhere('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (!$user || !Auth::attempt(['email' => $user->email, 'password' => $credentials['password']])) {
            return response()->json(['message' => 'Login credentials and password not recognized.'], 422);
        }

        // Create token for the authenticated user
        $tokenResult = $user->createToken($user->username ?? $user->email ?? $user->phone);
        $token = $tokenResult->plainTextToken;

        // Get the authenticated user resource
        $user_resource = new UserResource($user);

        return response()->json(['user' => $user_resource, 'token' => $token, 'message' => 'User Logged In Successfully'], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->success($request->user(), 'User Logged Out Successfully');
    }
}
