<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Otp\UserRegistrationOtp;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use SadiqSalau\LaravelOtp\Facades\Otp;
use Tymon\JWTAuth\Facades\JWTAuth;
use Google\Client as GoogleClient;

class AuthController extends Controller
{
    const SUCCESS_LOGIN_MESSAGE = 'Successfully Logged In';
    const ERROR_LOGIN_MESSAGE = 'Error while logging in';
    const SUCCESS_REGISTER_MESSAGE = 'User Successfully Registered';
    const ERROR_REGISTER_MESSAGE = 'Failed to Regist User';

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'name' => 'nullable|string',
            'password' => 'required|string|min:8',
        ]);

        $emailOrName = $request->input('email') ?? $request->input('name');
        $inputField = filter_var($emailOrName, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $credentials = [
            $inputField => $emailOrName,
            'password' => $request->input('password'),
        ];

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return $this->respondWithToken($token);
    }


    public function register(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $email = strtolower($request->email);

            Cache::put("register_payload_{$email}", [
                'name'     => $request->name,
                'email'    => $email,
                'password' => $request->password, 
            ], now()->addMinutes(10)); 

            $otp = Otp::identifier($email)->send(
                new UserRegistrationOtp(
                    name: $request->name,
                    email: $email,
                    password: $request->password
                ),
                Notification::route('mail', $email)
            );

            return response()->json([
                'success' => true,
                'message' => 'OTP berhasil dikirim. Silakan cek email Anda untuk verifikasi.',
                'status' => $otp['status'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function logout()
    {
    JWTAuth::invalidate(JWTAuth::getToken());
    return response()->json(['message' => 'Successfully logged out']);
    }


    public function me()
    {
    return response()->json(JWTAuth::parseToken()->authenticate());
    }


   public function refresh()
    {
    $newToken = JWTAuth::parseToken()->refresh();
    return $this->respondWithToken($newToken);
    }

    protected function respondWithToken($token)
{
    return response()->json([
        'access_token' => $token,
        'token_type' => 'bearer',
        'expires_in' => JWTAuth::factory()->getTTL() * 60,
        'user' => JWTAuth::user(),
    ]);
    }

}
