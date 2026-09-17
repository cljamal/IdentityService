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

    public function put(string $subject, string $code): void
    {
        // MULTI/EXEC — иначе сбой между двумя SETEX мог бы записать код
        // без cooldown и тем самым обойти "один код в минуту".
        Redis::transaction(function ($tx) use ($subject, $code) {
            $tx->setex($this->codeKey($subject), self::CODE_TTL, $code);
            $tx->setex($this->cooldownKey($subject), self::RESEND_COOLDOWN, 1);
        });
    }

    public function get(string $subject): ?string
    {
        return Redis::get($this->codeKey($subject)) ?: null;
    }

    public function forget(string $subject): void
    {
        Redis::del($this->codeKey($subject));
    }

    public function canBeRequested(string $subject): bool
    {
        return ! Redis::exists($this->cooldownKey($subject));
    }

    public function secondsUntilNextRequest(string $subject): int
    {
        return max(0, Redis::ttl($this->cooldownKey($subject)));
    }

    private function codeKey(string $subject): string
    {
        return "otp:code:{$subject}";
    }

    private function cooldownKey(string $subject): string
    {
        return "otp:cooldown:{$subject}";
    }
}
