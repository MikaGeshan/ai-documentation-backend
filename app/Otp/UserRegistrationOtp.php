<?php

namespace App\Otp;

use SadiqSalau\LaravelOtp\Contracts\OtpInterface as Otp;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use App\Models\User;

class UserRegistrationOtp implements Otp
{
    /**
     * Constructs Otp class
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password
    ) {
        //
    }

    /**
     * Processes the Otp
     *
     * @return User
     */ 
    public function process()
    {
        /** @var User */
        $user = User::unguarded(function () {
            return User::create([
                'name'                  => $this->name,
                'email'                 => $this->email,
                'password'              => Hash::make($this->password),
                'email_verified_at'     => now(),
            ]);
        });

        event(new Registered($user));

        Auth::login($user);

        return $user;
    }
}
