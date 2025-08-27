<?php

namespace App\Services;

use App\Models\User;
use Google_Client;
use Google\Service\Drive as Google_Service_Drive;
use Illuminate\Support\Facades\Log;

class GoogleTokenService
{

    /**
     * Get authorized Google client for a specific email
     */
    public static function getAuthorizedClient(string $accountEmail): Google_Client
    {
        $user = User::where('email', $accountEmail)->first();

        if (!$user || !$user->access_token) {
            throw new \Exception("No stored Google tokens for {$accountEmail}");
        }

        $client = new Google_Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->addScope(Google_Service_Drive::DRIVE);
        $client->setAccessType('offline');
        $client->setAccessToken([
            'access_token' => $user->access_token,
            'refresh_token' => $user->refresh_token,
            'expires_in'    => $user->expires_at ? now()->diffInSeconds($user->expires_at) : 3600,
            'created'       => now()->timestamp
        ]);

        if ($client->isAccessTokenExpired()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($user->refresh_token);

            if (!isset($newToken['access_token'])) {
                throw new \Exception("Failed to refresh Google token for {$accountEmail}");
            }

            $user->access_token = $newToken['access_token'];
            $user->expires_at = now()->addSeconds($newToken['expires_in']);
            $user->save();

            $client->setAccessToken($newToken);
        }

        return $client;
    }

    /**
     * Exchange serverAuthCode for tokens and store them
     */
    public static function exchangeAuthCodeAndStore(string $accountEmail, string $serverAuthCode): array
    {
        $client = new Google_Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $client->addScope(Google_Service_Drive::DRIVE);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $tokenData = $client->fetchAccessTokenWithAuthCode($serverAuthCode);

        if (isset($tokenData['error'])) {
            Log::error("[GoogleTokenService] Failed to exchange auth code", ['error' => $tokenData]);
            throw new \Exception("Google Auth code exchange failed: " . $tokenData['error']);
        }

        $user = User::where('email', $accountEmail)->firstOrFail();
        $user->access_token = $tokenData['access_token'];
        $user->refresh_token = $tokenData['refresh_token'] ?? $user->refresh_token; 
        $user->expires_at = now()->addSeconds($tokenData['expires_in']);
        $user->save();

        Log::info("[GoogleTokenService] Tokens stored for {$accountEmail}");

        return $tokenData;
    }

    /**
     * Attempt to fetch Google tokens for a given email silently.
     * Returns stored tokens after exchange, or null if not available.
     */
    public static function checkAndStoreGoogleTokens(string $email): ?array
    {
        try {
            $client = new Google_Client();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
            $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
            $client->setScopes(['openid', 'email', 'profile', Google_Service_Drive::DRIVE]);
            $client->setAccessType('offline');
            $client->setPrompt('consent');

            $client->setLoginHint($email);

            $user = User::where('email', $email)->first();

            if ($user && $user->access_token) {
                $client->setAccessToken([
                    'access_token'  => $user->access_token,
                    'refresh_token' => $user->refresh_token,
                    'expires_in'    => $user->expires_at ? now()->diffInSeconds($user->expires_at) : 3600,
                    'created'       => now()->timestamp
                ]);

                if (!$client->isAccessTokenExpired()) {
                    return $user->only(['access_token', 'refresh_token']);
                }

                // Refresh token if expired
                $newToken = $client->fetchAccessTokenWithRefreshToken($user->refresh_token);
                $user->access_token = $newToken['access_token'];
                $user->expires_at = now()->addSeconds($newToken['expires_in']);
                $user->save();
                return $newToken;
            }

            // If user has no token yet, we try to silently get the serverAuthCode
            // NOTE: fully backend-only silent exchange may fail due to Google OAuth consent
            $authUrl = $client->createAuthUrl([
                'login_hint' => $email,
                'access_type' => 'offline',
                'prompt' => 'consent',
            ]);

            Log::info("[GoogleTokenService] Need user consent to fetch serverAuthCode", ['authUrl' => $authUrl]);

            return null;

        } catch (\Throwable $e) {
            Log::error("[GoogleTokenService] Failed to check/store tokens for {$email}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

     /**
     * Generate consent URL to authorized the user.
     */
   public static function getTokensOrAuthURL(string $email): ?array
{
    $user = User::where('email', $email)->first();

    // Determine redirect URI
    $redirectUri = env('GOOGLE_REDIRECT_URI'); // default localhost
    if (request()->get('mobile')) { // check mobile param
        $redirectUri = 'com.aidocumentation:/google-auth-success';
    }

    // If user not found or no access token, generate consent URL
    if (!$user || !$user->access_token) {
        $client = new Google_Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri($redirectUri); // use dynamic redirect
        $client->setScopes(['openid', 'email', 'profile', Google_Service_Drive::DRIVE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setLoginHint($email);

        $authUrl = $client->createAuthUrl();
        return ['auth_url' => $authUrl]; 
    }

    // Existing logic for refreshing token...
    $client = new Google_Client();
    $client->setClientId(env('GOOGLE_CLIENT_ID'));
    $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
    $client->setScopes(['openid', 'email', 'profile', Google_Service_Drive::DRIVE]);
    $client->setAccessType('offline');

    $client->setAccessToken([
        'access_token'  => $user->access_token,
        'refresh_token' => $user->refresh_token,
        'expires_in'    => $user->expires_at ? now()->diffInSeconds($user->expires_at) : 3600,
        'created'       => now()->timestamp,
    ]);

    if ($client->isAccessTokenExpired()) {
        $newToken = $client->fetchAccessTokenWithRefreshToken($user->refresh_token);
        if (!isset($newToken['access_token'])) {
            $client->setRedirectUri($redirectUri); 
            $authUrl = $client->createAuthUrl([
                'login_hint' => $email,
                'access_type' => 'offline',
                'prompt' => 'consent',
            ]);
            return ['auth_url' => $authUrl];
        }

        $user->access_token = $newToken['access_token'];
        $user->expires_at = now()->addSeconds($newToken['expires_in']);
        $user->save();
        $client->setAccessToken($newToken);
    }

    return [
        'access_token'  => $client->getAccessToken()['access_token'],
        'refresh_token' => $user->refresh_token,
        'expires_at'    => $user->expires_at,
    ];
}


    /**
     * Get current access token string for a given account.
     */
    public static function getAccessToken(string $accountEmail): string
    {
        $client = self::getAuthorizedClient($accountEmail);
        return $client->getAccessToken()['access_token'];
    }

}
