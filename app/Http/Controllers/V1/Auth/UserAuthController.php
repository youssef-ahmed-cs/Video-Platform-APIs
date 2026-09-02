<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Mail\WelcomeEmailMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserAuthController extends Controller
{
    public function register(RegisterUserRequest $request)
    {
        $registerUserData = $request->validated();
        $user = User::create([
            'name' => $registerUserData['name'],
            'email' => $registerUserData['email'],
            'password' => Hash::make($registerUserData['password']),
            'username' => $registerUserData['username']
                ?? str_replace('.', '_', Str::before($registerUserData['email'], '@').rand(1000, 9999)),]);

        $token = $user->createToken($user->name . '-AuthToken')->plainTextToken;
        Log::info('New user registered: ' . $user->email);

        return response()->json([
            'message' => 'User Created Successfully!',
            'user' => new UserResource($user),
            'access_token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $loginUserData = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|min:8'
        ]);

        $user = User::where('email', $loginUserData['email'])->first();
        if (!$user || !Hash::check($loginUserData['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid Credentials if you forget your password use forget password endpoint!',
            ], 401);
        }

        $token = $user->createToken($user->name . '-AuthToken')->plainTextToken;
        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
        ]);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();
        Log::info('User logged out: ' . auth()->user()->email);

        return response()->json([
            "message" => "logged out successfully!"
        ]);
    }

    public function user()
    {
        if (!auth()->user()) {
            return response()->json([
                "message" => "User not found"
            ]);
        }

        return response()->json([
            "user" => new UserResource(auth()->user()),
        ]);
    }

    public function refreshToken()
    {
        $user = auth()->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken($user->name . '-AuthToken')->plainTextToken;

        return response()->json([
            'access_token' => $token,
        ]);
    }
}
