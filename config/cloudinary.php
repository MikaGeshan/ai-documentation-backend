<?php

return [
    'cloud' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME', 'my_cloud_name'),
        'api_key'    => env('CLOUDINARY_API_KEY', 'my_key'),
        'api_secret' => env('CLOUDINARY_API_SECRET', 'my_secret'),
    ],
    'url' => [
        'secure' => true,
        'cname'  => env('CLOUDINARY_CNAME', null),
    ],
];
