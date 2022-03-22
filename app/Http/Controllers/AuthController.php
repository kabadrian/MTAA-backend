<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request){
        $validated_form = $request->validate([
            'name' => 'required|string',
            'email' => 'required|unique:users,email|string',
            'password' => 'required|string|confirmed',
        ]);

        $user = User::create([
            'name' => $validated_form['name'],
            'email' => $validated_form['email'],
            'password' => bcrypt($validated_form['password'])
        ]);

        $token = $user->createToken('apiToken')->plainTextToken;

        $response_body = [
            'user' => $user,
            'token' => $token
        ];

        return response($response_body, 201);
    }

    public function login(Request $request){
        $validated_form = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated_form['email'])->first();

        if(!$user || !Hash::check($validated_form['password'], $user->password)){
            return response(['message'=> 'Incorrect email or password'], 400);
        }

        $token = $user->createToken('apiToken')->plainTextToken;

        $response_body = [
            'user' => $user,
            'token' => $token
        ];

        return response($response_body, 200);
    }

    public function logout(Request $request){
        $user = Auth::user();

        $user->tokens()->delete();

        return [
            'message' => 'logged out',
        ];
    }
}
