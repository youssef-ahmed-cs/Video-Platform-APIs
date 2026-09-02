<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AdminRestoreAccountRequestMail;
use App\Mail\RestoreAccountOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class RestoreDeletedAccountController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|email:dns,rfc',
            'account_number' => 'required|string',
        ]);

        $user = User::withTrashed()
            ->where('email', $request->email)
            ->where('account_number', $request->account_number)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No deleted account found for this email and account number.',
            ], 404);
        }

        if (!$user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'This account is already active.',
            ], 400);
        }

        $otp = (string)random_int(100000, 999999);
        $redisKey = 'account_restore_otp:' . $request->email . ':' . $request->account_number;

        Redis::set($redisKey, $otp);
        Redis::expire($redisKey, 300);

        Mail::to($user->email)->send(new RestoreAccountOtpMail($otp));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully. It expires in 5 minutes.',
        ]);
    }

    public function verifyOtpAndRestore(Request $request)
    {
        $request->validate([
            'email' => 'required|email|email:dns,rfc',
            'account_number' => 'required|string',
            'otp' => 'required|digits:6',
        ]);

        $redisKey = 'account_restore_otp:' . $request->email . ':' . $request->account_number;
        $cachedOtp = Redis::get($redisKey);

        if (!$cachedOtp || $cachedOtp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        $user = User::withTrashed()
            ->where('email', $request->email)
            ->where('account_number', $request->account_number)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No deleted account found for this email and account number.',
            ], 404);
        }

        if (!$user->trashed()) {
            Redis::del($redisKey);

            return response()->json([
                'success' => false,
                'message' => 'This account is already active.',
            ], 400);
        }

        $user->restore();
        Redis::del($redisKey);

        return response()->json([
            'success' => true,
            'message' => 'Account restored successfully.',
        ]);
    }

    public function requestAdminActivation(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|email:dns,rfc',
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255',
            'last_login_at' => 'nullable|date',
            'reason' => 'required|string|max:2000',
        ]);

        $user = User::withTrashed()
            ->where('email', $validated['email'])
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found for this email.',
            ], 404);
        }

        if (!$user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'This account is already active.',
            ], 400);
        }

        Mail::to('active@dev-hubs.tech')->send(
            new AdminRestoreAccountRequestMail([
                'user_id' => $user->id,
                'email' => $validated['email'],
                'name' => $validated['name'],
                'username' => $validated['username'] ?? null,
                'account_number' => $user->account_number,
                'deleted_at' => $user->deleted_at?->toDateTimeString(),
                'last_login_at' => $validated['last_login_at'] ?? null,
                'reason' => $validated['reason'],
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Your request has been sent to active@dev-hubs.tech for manual account activation , please wait we will reach you within on or two days!.',
        ]);
    }
}
