<?php

namespace App\Auth\Strategies\Concerns;

trait GeneratesVerificationCode
{
    private function generateCode(): string
    {
        if (app()->environment('local')) {
            return '1111';
        }

        return (string) random_int(1000, 9999);
    }
}
