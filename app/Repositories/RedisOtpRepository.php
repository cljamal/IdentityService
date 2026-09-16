<?php

namespace App\Repositories;

use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\Redis;

class RedisOtpRepository implements OtpRepositoryInterface
{
    /** Code validity: 1:30. */
    private const CODE_TTL = 90;

    /** Resend cooldown: one code per minute. */
    private const RESEND_COOLDOWN = 60;

    public function put(string $phone, string $code): void
    {
        Redis::setex($this->codeKey($phone), self::CODE_TTL, $code);
        Redis::setex($this->cooldownKey($phone), self::RESEND_COOLDOWN, 1);
    }

    public function get(string $phone): ?string
    {
        return Redis::get($this->codeKey($phone)) ?: null;
    }

    public function forget(string $phone): void
    {
        Redis::del($this->codeKey($phone));
    }

    public function canBeRequested(string $phone): bool
    {
        return ! Redis::exists($this->cooldownKey($phone));
    }

    public function secondsUntilNextRequest(string $phone): int
    {
        return max(0, Redis::ttl($this->cooldownKey($phone)));
    }

    private function codeKey(string $phone): string
    {
        return "otp:code:{$phone}";
    }

    private function cooldownKey(string $phone): string
    {
        return "otp:cooldown:{$phone}";
    }
}
