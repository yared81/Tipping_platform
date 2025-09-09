<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'data' => $request->user()->fresh(),
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        // Helper to delete the old avatar if it's stored locally
        $deleteOldAvatar = function () use ($user) {
            if ($user->avatarIsLocal() && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
        };

        // 1) Handle explicit removal
        if ($request->boolean('avatar_remove')) {
            $deleteOldAvatar();
            $data['avatar'] = null;
        }
        // 2) Handle uploaded file
        elseif ($request->hasFile('avatar')) {
            $deleteOldAvatar();

            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }
        // 3) Handle remote URL
        elseif ($request->filled('avatar_url')) {
            $avatarUrl = trim($request->input('avatar_url'));
            $deleteOldAvatar();

            $data['avatar'] = $avatarUrl;
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data'    => $user->fresh(),
        ]);
    }
}
