<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'secret'   => 'nullable|string', // NEW: only for admin creation
        ]);

        // Default role
        $role = 'tipper';

        // If secret provided and matches, upgrade role to admin
        if (!empty($validated['secret']) && $validated['secret'] === env('ADMIN_SECRET')) {
            $role = 'admin';
        } elseif ($request->has('role') && in_array($request->role, ['tipper', 'creator'])) {
            // allow tipper/creator choice if given
            $role = $request->role;
        }

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $role,
        ]);

        // Fire event to send verification email
        event(new Registered($user));

        // Issue token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully. Please verify your email.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }
}
