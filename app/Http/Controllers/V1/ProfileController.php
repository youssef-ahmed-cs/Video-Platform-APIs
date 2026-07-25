<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\UpdatePasswordNoticeMail;
use App\Models\User;
use App\Services\HackCDNStorage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class ProfileController extends Controller
{
    public function uploadAvatarImage(ImageUploadRequest $request, HackCDNStorage $hackCdnStorage)
    {
        $file = request()->file('file');

        $authuser = auth()->user();

        if (!$file) {
            return response()->json(['error' => 'No file provided'], 400);
        }

        $filePath = $hackCdnStorage->uploadImage($file, 'avatar', $authuser->name ?? null);
        $authuser->update(['avatar_url' => $filePath]);

        if (!$filePath) {
            return response()->json(['error' => 'Failed to upload file to Hack Club CDN'], 500);
        }

        return response()->json([
            'message' => 'Avatar image uploaded successfully!',
            'avatar_url' => $filePath,
        ]);
    }

    public function show()
    {
        $user = auth()->user();
        return response()->json(['user' => new UserResource($user)]);
    }

    public function removeAvatarImage(HackCDNStorage $hackCdnStorage)
    {
        $authuser = auth()->user();

        if (!$authuser->avatar_url) {
            return response()->json(['error' => 'No image provided'], 400);
        }

        $filePath = $hackCdnStorage->deleteUpload($authuser->avatar_url);
        $authuser->update(['avatar_url' => null]);

        return response()->json([
            'message' => 'Avatar image removed successfully!'
        ]);

    }

    public function update(UpdateProfileRequest $request, HackCDNStorage $hackCdnStorage)
    {
        $authuser = auth()->user();
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filePath = $hackCdnStorage->uploadImage($file, 'avatar', $authuser->name ?? null);
            if (!$filePath) {
                return response()->json(['error' => 'Failed to upload file to Hack Club CDN'], 500);
            }
            $data['avatar_url'] = $filePath;
        }

        $authuser->update($data);

        return response()->json([
            'message' => 'Profile updated successfully!',
            'user' => new UserResource($authuser),
        ]);

    }

    public function deleteProfilePermanently(HackCDNStorage $hackCdnStorage)
    {
        $authuser = auth()->user();

        if ($authuser->avatar_url) {
            $hackCdnStorage->deleteUpload($authuser->avatar_url);
        }

        $authuser->tokens()->delete();
        $authuser->forceDelete();


        return response()->json([
            'message' => 'Account deleted successfully!'
        ]);
    }

    public function softDeleteProfile(HackCDNStorage $hackCdnStorage)
    {
        $authuser = auth()->user();

        if ($authuser->avatar_url) {
            $hackCdnStorage->deleteUpload($authuser->avatar_url);
        }

        $authuser->tokens()->delete();
        $authuser->delete();


        return response()->json([
            'message' => 'Account deleted successfully!'
        ]);
    }

    public function restore(User $user) // admin only can restore accounts!
    {
        if (!Gate::allows('restore-user', $user)) {
            return response()->json([
                'error' => 'You do not have permission to restore this user'
            ], 403);
        }

        if (!$user->trashed()) {
            return response()->json([
                'message' => 'Account is not deleted.'
            ], 400);
        }

        $user->restore();

        return response()->json([
            'message' => 'Account restored successfully!'
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = auth()->user();

        $data = $request->validated();

        $user->update([
            'password' => Hash::make($data['new_password']),
        ]);

        Mail::to($user->email)->send(new UpdatePasswordNoticeMail($user));
        Log::notice('User password updated: ' . $user->email);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }
}
