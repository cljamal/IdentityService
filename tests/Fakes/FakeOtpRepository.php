<?php

namespace Tests\Fakes;

use App\Exceptions\Auth\InvalidOtpException;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Closure;

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

    /** @var array<string, bool> */
    private array $claimed = [];

    /** @var array<string, int> */
    private array $versions = [];

    public function put(string $subject, string $code): void
    {
        $this->versions[$subject] = ($this->versions[$subject] ?? 0) + 1;

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

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function consume(string $subject, string $code, Closure $operation): mixed
    {
        $actual = $this->get($subject);

        if ($actual === null || ! hash_equals($actual, $code) || isset($this->claimed[$subject])) {
            throw new InvalidOtpException;
        }

        $this->claimed[$subject] = true;
        $version = $this->versions[$subject];

        try {
            $result = $operation();
        } catch (\Throwable $exception) {
            unset($this->claimed[$subject]);

            throw $exception;
        }

        if (($this->versions[$subject] ?? null) === $version) {
            $this->forget($subject);
        } else {
            unset($this->claimed[$subject]);
        }

        return $result;
    }

    public function extendTtlIfCurrent(string $subject, string $expected): bool
    {
        $entry = $this->codes[$subject] ?? null;

        if ($entry === null || $entry['value'] !== $expected || $entry['expires_at'] <= now()->timestamp) {
            return false;
        }

        $this->codes[$subject]['expires_at'] = now()->timestamp + self::CODE_TTL;

        return true;
    }

    public function replaceIfUnclaimed(string $subject, string $value): bool
    {
        if (isset($this->claimed[$subject])) {
            return false;
        }

        $this->put($subject, $value);

        return true;
    }

    public function issueWithCooldownAndReplacePending(
        string $challengeSubject,
        string $code,
        string $cooldownSubject,
        string $pendingSubject,
        string $pendingValue,
    ): int {
        if (isset($this->claimed[$pendingSubject])) {
            return 1;
        }

        $retryAfter = $this->secondsUntilNextRequest($cooldownSubject);

        if ($retryAfter > 0) {
            return $retryAfter;
        }

        $this->put($challengeSubject, $code);
        $this->put($pendingSubject, $pendingValue);
        $this->cooldowns[$cooldownSubject] = now()->timestamp + self::RESEND_COOLDOWN;

        return 0;
    }

    public function forget(string $subject): void
    {
        unset($this->codes[$subject], $this->versions[$subject], $this->claimed[$subject]);
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

    public function peekLatest(string $prefix): ?string
    {
        $matches = array_filter(
            $this->codes,
            static fn (array $entry, string $subject): bool => str_starts_with($subject, $prefix),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($matches === []) {
            return null;
        }

        $latest = null;

        foreach ($matches as $entry) {
            $latest = $entry['value'];
        }

        return $latest;
    }

    public function peekVersion(string $subject): int
    {
        return $this->versions[$subject] ?? 0;
    }
}
