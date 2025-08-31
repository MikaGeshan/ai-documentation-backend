<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
        public function boot(): void
    {
        $sslPath = storage_path('ssl');
        if (!file_exists($sslPath)) {
            mkdir($sslPath, 0700, true);
        }

        if (env('MYSQL_ATTR_SSL_CA_B64')) {
            file_put_contents($sslPath.'/server-ca.pem', base64_decode(env('MYSQL_ATTR_SSL_CA_B64')));
        }

        if (env('MYSQL_ATTR_SSL_CERT_B64')) {
            file_put_contents($sslPath.'/client-cert.pem', base64_decode(env('MYSQL_ATTR_SSL_CERT_B64')));
        }

        if (env('MYSQL_ATTR_SSL_KEY_B64')) {
            file_put_contents($sslPath.'/client-key.pem', base64_decode(env('MYSQL_ATTR_SSL_KEY_B64')));
        }
    }

}
