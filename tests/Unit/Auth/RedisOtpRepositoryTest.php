<?php

namespace Tests\Unit\Auth;

use App\Exceptions\Auth\InvalidOtpException;
use App\Repositories\RedisOtpRepository;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class RedisOtpRepositoryTest extends TestCase
{
    public function test_production_repository_releases_only_its_own_versioned_claim_after_callback_failure(): void
    {
        $redis = new ManagedRedisOtpConnectionDouble;
        $redis->values = [
            'otp:code:subject' => '1234',
            'otp:version:subject' => 'generation-1',
        ];
        Redis::shouldReceive('connection')->andReturn($redis);
        $repository = new RedisOtpRepository;

        $caughtException = null;
        try {
            $repository->consume('subject', 'wrong', static fn (): null => null);
        } catch (InvalidOtpException) {
            $caughtException = new InvalidOtpException;
        }
        $this->assertInstanceOf(InvalidOtpException::class, $caughtException);
        $this->assertSame('1234', $redis->values['otp:code:subject']);
        $this->assertArrayNotHasKey('otp:claim:subject', $redis->values);

        $caughtException = null;
        try {
            $repository->consume('subject', '1234', static function (): never {
                throw new RuntimeException('Protected operation failed.');
            });
        } catch (RuntimeException $exception) {
            $caughtException = $exception;
        }
        $this->assertInstanceOf(RuntimeException::class, $caughtException);
        $this->assertSame('Protected operation failed.', $caughtException->getMessage());

        $this->assertArrayNotHasKey('otp:claim:subject', $redis->values);

        $nestedRejected = false;
        $result = $repository->consume('subject', '1234', function () use ($repository, &$nestedRejected): string {
            try {
                $repository->consume('subject', '1234', static fn (): null => null);
            } catch (InvalidOtpException) {
                $nestedRejected = true;
            }

            return 'completed';
        });

        $this->assertSame('completed', $result);
        $this->assertTrue($nestedRejected);
        $this->assertArrayNotHasKey('otp:code:subject', $redis->values);

        $redis->values = [
            'otp:code:subject' => '2468',
            'otp:version:subject' => 'generation-4',
        ];
        $repository->consume('subject', '2468', function () use ($redis): void {
            $redis->values['otp:code:subject'] = '1357';
            $redis->values['otp:version:subject'] = 'generation-5';
        });

        $this->assertSame('1357', $redis->values['otp:code:subject']);
        $this->assertSame('generation-5', $redis->values['otp:version:subject']);
        $this->assertArrayNotHasKey('otp:claim:subject', $redis->values);
        $this->assertSame('v2-consumed', $repository->consume(
            'subject',
            '1357',
            static fn (): string => 'v2-consumed',
        ));
        $this->assertArrayNotHasKey('otp:code:subject', $redis->values);
    }

    public function test_old_request_does_not_delete_a_claim_owned_by_another_request(): void
    {
        $redis = new ManagedRedisOtpConnectionDouble;
        $redis->values = [
            'otp:code:subject' => '5678',
            'otp:version:subject' => 'generation-2',
        ];
        Redis::shouldReceive('connection')->andReturn($redis);
        $repository = new RedisOtpRepository;

        $repository->consume('subject', '5678', function () use ($redis): void {
            $redis->values['otp:code:subject'] = '9999';
            $redis->values['otp:version:subject'] = 'generation-3';
            $redis->values['otp:claim:subject'] = 'another-owner:generation-3';
        });

        $this->assertSame('9999', $redis->values['otp:code:subject']);
        $this->assertSame('generation-3', $redis->values['otp:version:subject']);
        $this->assertSame('another-owner:generation-3', $redis->values['otp:claim:subject']);
    }

    public function test_production_repository_reserves_phone_change_cooldown_and_pending_in_one_command(): void
    {
        $redis = new ManagedRedisOtpConnectionDouble;
        Redis::shouldReceive('connection')->andReturn($redis);
        $repository = new RedisOtpRepository;

        $this->assertSame(0, $repository->issueWithCooldownAndReplacePending(
            'old:attempt-a',
            '1111',
            'old-cooldown:user-a',
            'pending:user-a',
            '{"attempt":"a","phone":"100"}',
        ));
        $this->assertSame(60, $repository->issueWithCooldownAndReplacePending(
            'old:attempt-b',
            '2222',
            'old-cooldown:user-a',
            'pending:user-a',
            '{"attempt":"b","phone":"200"}',
        ));
        $this->assertSame('{"attempt":"a","phone":"100"}', $redis->values['otp:code:pending:user-a']);
        $this->assertSame('1111', $redis->values['otp:code:old:attempt-a']);
        $this->assertArrayNotHasKey('otp:code:old:attempt-b', $redis->values);
        $this->assertSame(0, $repository->issueWithCooldownAndReplacePending(
            'old:attempt-c',
            '3333',
            'old-cooldown:user-b',
            'pending:user-b',
            '{"attempt":"c","phone":"300"}',
        ));
    }
}

/** Executes the Redis command contract used by RedisOtpRepository without connecting to Redis. */
final class ManagedRedisOtpConnectionDouble
{
    /** @var array<string, string> */
    public array $values = [];

    public function eval(string $script, int $numberOfKeys, mixed ...$arguments): int
    {
        $keys = array_slice($arguments, 0, $numberOfKeys);
        $values = array_slice($arguments, $numberOfKeys);

        if (str_contains($script, 'KEYS[7]')) {
            [$challengeCodeKey, $challengeVersionKey, $challengeCooldownKey, $cooldownKey, $pendingCodeKey, $pendingVersionKey, $pendingClaimKey] = $keys;
            [$codeTtl, $code, $challengeVersion, $cooldownTtl, $pending, $pendingVersion] = $values;
            $this->assertCommand($numberOfKeys === 7 && (int) $codeTtl === 90 && (int) $cooldownTtl === 60);

            if (isset($this->values[$pendingClaimKey])) {
                return 1;
            }

            if (isset($this->values[$cooldownKey])) {
                return 60;
            }

            $this->values[$challengeCodeKey] = $code;
            $this->values[$challengeVersionKey] = $challengeVersion;
            $this->values[$challengeCooldownKey] = '1';
            $this->values[$cooldownKey] = '1';
            $this->values[$pendingCodeKey] = $pending;
            $this->values[$pendingVersionKey] = $pendingVersion;

            return 0;
        }

        if (str_contains($script, 'current ~= ARGV[1]')) {
            [$codeKey, $claimKey, $versionKey] = $keys;
            [$code, $claim, $ttl] = $values;
            $this->assertCommand($numberOfKeys === 3 && (int) $ttl === 90);

            if (($this->values[$codeKey] ?? null) !== $code || isset($this->values[$claimKey]) || ! isset($this->values[$versionKey])) {
                return 0;
            }

            $this->values[$claimKey] = "{$claim}:{$this->values[$versionKey]}";

            return 1;
        }

        if (str_contains($script, 'string.sub(current_claim')) {
            [$codeKey, $claimKey, $versionKey] = $keys;
            [$claim] = $values;
            $currentClaim = $this->values[$claimKey] ?? null;

            if (! is_string($currentClaim) || ! str_starts_with($currentClaim, "{$claim}:")) {
                return 0;
            }

            if ($currentClaim !== "{$claim}:".($this->values[$versionKey] ?? '')) {
                unset($this->values[$claimKey]);

                return 2;
            }

            unset($this->values[$codeKey], $this->values[$claimKey], $this->values[$versionKey]);

            return 1;
        }

        if (str_contains($script, 'string.sub(current, 1')) {
            [$claimKey] = $keys;
            [$claim] = $values;
            $currentClaim = $this->values[$claimKey] ?? null;

            if (is_string($currentClaim) && str_starts_with($currentClaim, "{$claim}:")) {
                unset($this->values[$claimKey]);

                return 1;
            }

            return 0;
        }

        throw new RuntimeException('Unexpected Redis command was passed to the test connection.');
    }

    private function assertCommand(bool $condition): void
    {
        if (! $condition) {
            throw new RuntimeException('Redis command arguments did not match the OTP contract.');
        }
    }
}
