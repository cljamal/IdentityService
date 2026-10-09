<?php

namespace Tests\Unit\Auth;

use App\Exceptions\Auth\InvalidOtpException;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class OtpRepositoryConsumptionTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_invalid_code_does_not_claim_or_consume_the_valid_challenge(): void
    {
        $otp = new FakeOtpRepository;
        $otp->put('subject', '1234');

        try {
            $otp->consume('subject', '9999', static fn (): null => null);
            $this->fail('An invalid code must not be accepted.');
        } catch (InvalidOtpException) {
            $this->assertSame('1234', $otp->get('subject'));
        }
    }

    public function test_concurrent_claim_is_refused_and_failed_operation_can_retry(): void
    {
        $otp = new FakeOtpRepository;
        $otp->put('subject', '1234');
        $nestedException = null;

        try {
            $otp->consume('subject', '1234', function () use ($otp, &$nestedException): void {
                $this->assertFalse($otp->replaceIfUnclaimed('subject', '5678'));

                try {
                    $otp->consume('subject', '1234', static fn (): null => null);
                } catch (InvalidOtpException $exception) {
                    $nestedException = $exception;
                }

                throw new RuntimeException('Database write failed.');
            });
        } catch (RuntimeException) {
            $this->assertInstanceOf(InvalidOtpException::class, $nestedException);
        }

        $this->assertSame('retry-result', $otp->consume('subject', '1234', static fn (): string => 'retry-result'));
        $this->assertNull($otp->get('subject'));

        $otp->put('subject', '1234');
        try {
            $otp->consume('subject', '1234', static function (): never {
                throw new RuntimeException('Retryable failure.');
            });
        } catch (RuntimeException) {
            $this->assertTrue($otp->replaceIfUnclaimed('subject', '5678'));
        }

        $this->assertSame('5678', $otp->get('subject'));
    }

    public function test_expiry_and_replacement_never_resurrect_or_consume_a_newer_code(): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00');
        $otp = new FakeOtpRepository;
        $otp->put('subject', '1111');
        Carbon::setTestNow(Carbon::now()->addSeconds(90));

        $this->assertNull($otp->get('subject'));
        $otp->put('subject', '2222');
        $result = $otp->consume('subject', '2222', function () use ($otp): string {
            $otp->put('subject', '3333');

            return 'accepted-old-generation';
        });

        $this->assertSame('accepted-old-generation', $result);
        $this->assertSame('3333', $otp->get('subject'));
        $this->assertSame(
            'new-generation-result',
            $otp->consume('subject', '3333', static fn (): string => 'new-generation-result'),
        );
    }
}
