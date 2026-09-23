<?php

namespace Tests\Fakes;

use App\Repositories\Contracts\OtpRepositoryInterface;

/**
 * In-memory stand-in for RedisOtpRepository, driven by the framework's
 * testable clock (Carbon::setTestNow()) instead of real time, so tests can
 * assert on TTL-related behavior deterministically. Mirrors its TTLs.
 */
class FakeOtpRepository implements OtpRepositoryInterface
{
    private const CODE_TTL = 90;

    private const RESEND_COOLDOWN = 60;

    /** @var array<string, array{value: string, expires_at: int}> */
    private array $codes = [];

    /** @var array<string, int> */
    private array $cooldowns = [];

    public function put(string $subject, string $code): void
    {
        $this->codes[$subject] = [
            'value' => $code,
            'expires_at' => now()->timestamp + self::CODE_TTL,
        ];

        $this->cooldowns[$subject] = now()->timestamp + self::RESEND_COOLDOWN;
    }

    public function get(string $subject): ?string
    {
        $entry = $this->codes[$subject] ?? null;

        if ($entry === null || $entry['expires_at'] <= now()->timestamp) {
            return null;
        }

        return $entry['value'];
    }

    public function forget(string $subject): void
    {
        unset($this->codes[$subject]);
    }

    public function canBeRequested(string $subject): bool
    {
        $cooldown = $this->cooldowns[$subject] ?? null;

        return $cooldown === null || $cooldown <= now()->timestamp;
    }

    public function secondsUntilNextRequest(string $subject): int
    {
        return max(0, ($this->cooldowns[$subject] ?? 0) - now()->timestamp);
    }

    /**
     * Test-only introspection: the raw stored value, ignoring TTL. Codes are
     * randomly generated in non-local environments, so tests need this to
     * read back what was actually issued.
     */
    public function peek(string $subject): ?string
    {
        return $this->codes[$subject]['value'] ?? null;
    }
}
