<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleTokenService;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use SadiqSalau\LaravelOtp\Facades\Otp;
use Google\Service\Drive as GoogleServiceDrive;
use Google\Client as GoogleClient;

class OtpController extends Controller
{
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        try {
            $email = strtolower($request->email);

            $isValid = Otp::identifier($email)->attempt($request->otp);
            if (!$isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP tidak valid atau sudah expired.',
                ], 401);
            }

            $payload = Cache::pull("register_payload_{$email}");
            if (!$payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ada atau expired',
                ], 404);
            }

            $user = User::where('email', $email)->first();
            if (!$user) {
                $user = User::create([
                    'name'     => $payload['name'],
                    'email'    => $payload['email'],
                    'password' => Hash::make($payload['password']),
                ]);
            }

            $googleResult = GoogleTokenService::getTokensOrAuthUrl($email);

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success'       => true,
                'message'       => 'OTP valid, akun berhasil diverifikasi dan login.',
                'access_token'  => $token,
                'token_type'    => 'bearer',
                'expires_in'    => JWTAuth::factory()->getTTL() * 60,
                'user'          => $user->only(['id', 'name', 'email']),
                'google_tokens' => $googleResult['tokens'] ?? null,
                'auth_url'      => $googleResult['auth_url'] ?? null,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function handleGoogleCallback(Request $request)
    {
        $code = $request->get('code');
        if (!$code) {
            return response()->json(['error' => 'No code provided'], 400);
        }

        try {
            $client = new GoogleClient();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
            $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
            $client->addScope(['openid', 'email', 'profile', GoogleServiceDrive::DRIVE]);

            $token = $client->fetchAccessTokenWithAuthCode($code);
            if (isset($token['error'])) {
                return response()->json(['error' => $token['error']], 400);
            }

            $client->setAccessToken($token);

            $oauth2 = new \Google\Service\Oauth2($client);
            $googleUser = $oauth2->userinfo->get();

            $user = User::updateOrCreate(
                ['email' => $googleUser->email],
                [
                    'name'          => $googleUser->name,
                    'google_id'     => $googleUser->id,
                    'google_tokens' => json_encode($token),
                ]
            );

            $jwtToken = Auth::guard('api')->login($user);

            if ($request->has('mobile')) {
                return redirect()->away("com.aidocumentation://auth/google/callback?token={$jwtToken}");
            }

            return $this->respondWithToken($jwtToken, $user);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Return JWT token with user info
     */
    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => JWTAuth::factory()->getTTL() * 60,
            'user'         => $user->only(['id', 'name', 'email']),
        ]);
    }
}
