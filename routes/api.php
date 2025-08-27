<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\OtpController;
use Illuminate\Support\Facades\Route;


// Auth Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [OtpController::class, 'verifyOtp']);
Route::post('/auth/google', [GoogleAuthController::class, 'handleSignInWithGoogle'])->middleware('api');
Route::post('/auth/google/get-token', [GoogleAuthController::class, 'getTokens']);
Route::get('/auth/google/callback', [OtpController::class, 'handleGoogleCallback']);

// Google Drive Routes
Route::get('/drive-contents', [GoogleDriveController::class, 'getDriveContents']);
Route::get('/download-docs', [GoogleDriveController::class, 'downloadGoogleDocs']);
Route::get('/view-docs', [GoogleDriveController::class, 'viewGoogleDocsAsPdf']);
Route::delete('/delete-docs', [GoogleDriveController::class, 'deleteGoogleDocs']);
Route::post('/create-folder', [GoogleDriveController::class, 'createGoogleDriveFolder']);
Route::post('/convert-docs', [GoogleDriveController::class, 'convertGoogleDocsToTxt']);
Route::post('/upload-docs', [GoogleDriveController::class, 'uploadFileToDrive']);

// Explore Routes       
Route::get('/explore-contents', [ExploreController::class, 'index']);   
Route::get('/explore-get-filters', [ExploreController::class, 'getFilter']);
Route::post('/create-explore', [ExploreController::class, 'store']);
Route::put('/update-explore/{id}', [ExploreController::class, 'update']);
Route::delete('/delete-explore/{id}', [ExploreController::class, 'destroy']);

// Auth Middleware
Route::middleware(['auth:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});


