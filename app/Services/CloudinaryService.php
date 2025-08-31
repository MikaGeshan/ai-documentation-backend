<?php

namespace App\Services;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

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
     *
     * @param string $filePath
     * @param string $folder
     * @return string URL of uploaded file
     */
    public function upload(string $filePath, string $folder = 'default'): string
    {
        $upload = new UploadApi();

        $result = $upload->upload($filePath, [
            'folder' => $folder
        ]);

        return $result['secure_url'] ?? '';
    }
}
