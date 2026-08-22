<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ForgetPassOtpMail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ForgetPasswordController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email|email:dns,rfc']);

        $otp = rand(100000, 999999);

        $redisKey = 'password_reset_otp:' . $request->email;
        Redis::set($redisKey, $otp);
        Redis::expire($redisKey, 300);

        Mail::to($request->email)->send(new ForgetPassOtpMail($otp));

        return response()->json(['message' => 'OTP sent successfully'], 200);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email|email:dns,rfc',
            'otp' => 'required|digits:6',
        ]);

        $redisKey = 'password_reset_otp:' . $request->email;
        $cachedOtp = Redis::get($redisKey);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        return response()->json(['message' => 'OTP verified successfully'], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email|email:dns,rfc',
            'password' => ['required', Password::defaults(),'confirmed'],
        ]);

        // Update the password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        Redis::del('password_reset_otp:' . $request->email);

        return response()->json(['message' => 'Password reset successfully'], 200);
    }
}
