<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleTokenService;
use Google_Client;
use Google\Service\Drive as Google_Service_Drive;
use Google\Service\Drive\Permission as Google_Service_Drive_Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class GoogleAuthController extends Controller
{
    private string $adminEmail;

    public function __construct()
    {
        $this->adminEmail = env('GOOGLE_ADMIN_EMAIL'); 
    }

    /**
     * Handle Google Sign-In via ID token
     */
    public function handleSignInWithGoogle(Request $request)
    {
        $idToken = $request->input('idToken');
        $serverAuthCode = $request->input('serverAuthCode');

        if (!$idToken || !$serverAuthCode) {
            return response()->json(['error' => 'Missing Google login parameters'], 400);
        }

        $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
        $payload = $client->verifyIdToken($idToken);

        if (!$payload) {
            return response()->json(['error' => 'Invalid ID Token'], 401);
        }

        $email = $payload['email'];
        $name  = $payload['name'];

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'google_id'         => $payload['sub'],
                'email_verified_at' => now(),
            ]
        );

        $tokenData = GoogleTokenService::exchangeAuthCodeAndStore($email, $serverAuthCode);

        if (!isset($tokenData['access_token'])) {
            return response()->json(['error' => 'Failed to exchange auth code'], 500);
        }

        $user->update([
            'access_token'  => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $user->refresh_token,
            'expires_at'    => now()->addSeconds($tokenData['expires_in'] ?? 3600),
        ]);

        $hasAccess = $this->checkFolderAccess($email);

        $user->role = $hasAccess ? 'admin' : 'user';
        $user->save();

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token'     => $token,
            'user'      => $user,
            'is_admin'  => $hasAccess
        ]);
    }

    /**
     * Check if a user has access to the admin Google Drive folder
     */
    protected function checkFolderAccess(string $email): bool
    {
        try {
            $client = GoogleTokenService::getAuthorizedClient($this->adminEmail);
            $drive = new Google_Service_Drive($client);
            $folderId = env('GOOGLE_DRIVE_FOLDER_ID');

            $permissions = $drive->permissions->listPermissions($folderId, [
                'fields' => 'permissions(emailAddress,role)',
            ]);

            foreach ($permissions->getPermissions() as $perm) {
                if ($perm->getEmailAddress() === $email) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to check folder access: " . $e->getMessage());
        }

        return false;
    }

   public function getTokens(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string',
        ]);

        $email = strtolower($request->input('email'));
        $serverAuthCode = urldecode($request->input('code'));

        try {
            $tokens = GoogleTokenService::exchangeAuthCodeAndStore($email, $serverAuthCode);

            $user = User::firstOrCreate(['email' => $email]);

            $user->update([
                'access_token'  => $tokens['access_token'] ?? $user->access_token,
                'refresh_token' => $tokens['refresh_token'] ?? $user->refresh_token,
                'expires_at'    => now()->addSeconds($tokens['expires_in'] ?? 3600),
            ]);

            $hasAccess = $this->checkFolderAccess($email);
            $user->role = $hasAccess ? 'admin' : 'user';
            $user->save();

            // Generate new JWT for user
            $jwtToken = JWTAuth::fromUser($user);

            return response()->json([
                'success'       => true,
                'message'       => 'Google tokens stored successfully',
                'google_tokens' => $tokens,
                'access_token'  => $jwtToken,
                'token_type'    => 'bearer',
                'expires_in'    => JWTAuth::factory()->getTTL() * 60,
                'user'          => $user->only(['id', 'name', 'email', 'role']),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to exchange Google code: ' . $e->getMessage(),
            ], 500);
        }
    }

}
