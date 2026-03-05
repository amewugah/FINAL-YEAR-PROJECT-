<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;
use Log;
class Register extends Controller
{
    // Register new users
    public function register(Request $request)
    {
        try{
        // Validate incoming request data
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|confirmed|string|min:8',
        ]);

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Generate JWT token for the newly registered user
        $token = JWTAuth::fromUser($user);

        // Respond with the user and token
        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token
        ], 201);
    }catch (Exception $e) {
        // Log error and return response
        return response()->json(['message' => 'Error saving record', 'error' => $e->getMessage()], 500);
    }
    }
}
