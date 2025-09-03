<?php

namespace App\Services;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    public function __construct()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_NAME'),
                'api_key'    => env('CLOUDINARY_KEY'),
                'api_secret' => env('CLOUDINARY_SECRET'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    /**
     * Upload file to Cloudinary
     */
    public function upload(string $filePath, string $folder = 'default'): string
    {
        $upload = new UploadApi();

        $result = $upload->upload($filePath, [
            'folder' => $folder
        ]);

        return $result['secure_url'] ?? '';
    }

    /**
     * Delete file from Cloudinary using its full URL
     */
    public function delete(string $imageUrl, string $folder = 'default'): bool
    {
        try {
            $path = parse_url($imageUrl, PHP_URL_PATH);
            $filename = pathinfo($path, PATHINFO_FILENAME);
            $publicId = $folder . '/' . $filename;

            (new UploadApi())->destroy($publicId);

            return true;
        } catch (\Exception $e) {
            Log::warning("Failed to delete Cloudinary image: " . $e->getMessage());
            return false;
        }
    }
}
