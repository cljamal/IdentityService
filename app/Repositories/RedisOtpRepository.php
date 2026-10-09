<?php

namespace App\Repositories;

use App\Exceptions\Auth\InvalidOtpException;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Closure;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

final class RedisOtpRepository implements OtpRepositoryInterface
{
    /** Code validity: 1:30. */
    private const int CODE_TTL = 90;

    /** Resend cooldown: one code per minute. */
    private const int RESEND_COOLDOWN = 60;

    public function put(string $subject, string $code): void
    {
        // @phpstan-ignore staticMethod.notFound
        Redis::transaction(function (\Redis $tx) use ($subject, $code): void {
            $tx->setex($this->codeKey($subject), self::CODE_TTL, $code);
            $tx->setex($this->versionKey($subject), self::CODE_TTL, (string) Str::uuid());
            $tx->setex($this->cooldownKey($subject), self::RESEND_COOLDOWN, 1);
        });
    }

    public function get(string $subject): ?string
    {
        return Redis::get($this->codeKey($subject)) ?: null;
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function consume(string $subject, string $code, Closure $operation): mixed
    {
        $claim = (string) Str::uuid();
        $claimScript = <<<'LUA'
local current = redis.call('GET', KEYS[1])
if not current or current ~= ARGV[1] then return 0 end
if redis.call('EXISTS', KEYS[2]) == 1 then return 0 end
local version = redis.call('GET', KEYS[3])
if not version then return 0 end
redis.call('SET', KEYS[2], ARGV[2] .. ':' .. version, 'EX', ARGV[3])
return 1
LUA;

        $claimed = $this->eval(
            $claimScript,
            3,
            $this->codeKey($subject),
            $this->claimKey($subject),
            $this->versionKey($subject),
            $code,
            $claim,
            self::CODE_TTL,
        );

        if ((int) $claimed !== 1) {
            throw new InvalidOtpException;
        }

        try {
            $result = $operation();
        } catch (\Throwable $exception) {
            $this->releaseClaim($subject, $claim);

            throw $exception;
        }

        $completeScript = <<<'LUA'
local current_claim = redis.call('GET', KEYS[2])
if not current_claim or string.sub(current_claim, 1, string.len(ARGV[1]) + 1) ~= ARGV[1] .. ':' then return 0 end
local version = redis.call('GET', KEYS[3])
if current_claim ~= ARGV[1] .. ':' .. (version or '') then
    redis.call('DEL', KEYS[2])
    return 2
end
redis.call('DEL', KEYS[1], KEYS[2], KEYS[3])
return 1
LUA;
        $this->eval(
            $completeScript,
            3,
            $this->codeKey($subject),
            $this->claimKey($subject),
            $this->versionKey($subject),
            $claim,
        );

        return $result;
    }

    public function extendTtlIfCurrent(string $subject, string $expected): bool
    {
        $script = <<<'LUA'
if redis.call('GET', KEYS[1]) ~= ARGV[1] then return 0 end
redis.call('EXPIRE', KEYS[1], ARGV[2])
redis.call('EXPIRE', KEYS[2], ARGV[2])
return 1
LUA;

        return (int) $this->eval(
            $script,
            2,
            $this->codeKey($subject),
            $this->versionKey($subject),
            $expected,
            self::CODE_TTL,
        ) === 1;
    }

    public function replaceIfUnclaimed(string $subject, string $value): bool
    {
        $script = <<<'LUA'
if redis.call('EXISTS', KEYS[2]) == 1 then return 0 end
redis.call('SETEX', KEYS[1], ARGV[1], ARGV[2])
redis.call('SETEX', KEYS[3], ARGV[1], ARGV[3])
redis.call('SETEX', KEYS[4], ARGV[4], 1)
return 1
LUA;

        return (int) $this->eval(
            $script,
            4,
            $this->codeKey($subject),
            $this->claimKey($subject),
            $this->versionKey($subject),
            $this->cooldownKey($subject),
            self::CODE_TTL,
            $value,
            (string) Str::uuid(),
            self::RESEND_COOLDOWN,
        ) === 1;
    }

    public function issueWithCooldownAndReplacePending(
        string $challengeSubject,
        string $code,
        string $cooldownSubject,
        string $pendingSubject,
        string $pendingValue,
    ): int {
        $script = <<<'LUA'
if redis.call('EXISTS', KEYS[7]) == 1 then return 1 end
if redis.call('EXISTS', KEYS[4]) == 1 then return math.max(1, redis.call('TTL', KEYS[4])) end
redis.call('SETEX', KEYS[1], ARGV[1], ARGV[2])
redis.call('SETEX', KEYS[2], ARGV[1], ARGV[3])
redis.call('SETEX', KEYS[3], ARGV[4], 1)
redis.call('SETEX', KEYS[4], ARGV[4], 1)
redis.call('SETEX', KEYS[5], ARGV[1], ARGV[5])
redis.call('SETEX', KEYS[6], ARGV[1], ARGV[6])
return 0
LUA;

        return (int) $this->eval(
            $script,
            7,
            $this->codeKey($challengeSubject),
            $this->versionKey($challengeSubject),
            $this->cooldownKey($challengeSubject),
            $this->cooldownKey($cooldownSubject),
            $this->codeKey($pendingSubject),
            $this->versionKey($pendingSubject),
            $this->claimKey($pendingSubject),
            self::CODE_TTL,
            $code,
            (string) Str::uuid(),
            self::RESEND_COOLDOWN,
            $pendingValue,
            (string) Str::uuid(),
        );
    }

    public function forget(string $subject): void
    {
        Redis::del($this->codeKey($subject), $this->versionKey($subject), $this->claimKey($subject));
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

    private function claimKey(string $subject): string
    {
        return "otp:claim:{$subject}";
    }

    private function versionKey(string $subject): string
    {
        return "otp:version:{$subject}";
    }

    /**
     * Use Laravel's Redis connection adapter, which adapts the key count and
     * variadic arguments to PhpRedis' eval(script, args, numKeys) signature.
     */
    private function eval(string $script, int $numberOfKeys, mixed ...$arguments): mixed
    {
        /** @var PhpRedisConnection $connection */
        $connection = Redis::connection();

        return $connection->eval($script, $numberOfKeys, ...$arguments);
    }

    private function releaseClaim(string $subject, string $claim): void
    {
        $script = <<<'LUA'
local current = redis.call('GET', KEYS[1])
if current and string.sub(current, 1, string.len(ARGV[1]) + 1) == ARGV[1] .. ':' then
    return redis.call('DEL', KEYS[1])
end
return 0
LUA;

        $this->eval($script, 1, $this->claimKey($subject), $claim);
    }
}
