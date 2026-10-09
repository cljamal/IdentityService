<?php

namespace App\Repositories\Contracts;

use Closure;

interface OtpRepositoryInterface
{
    /**
     * Store the code for the given subject and start the resend cooldown.
     */
    public function put(string $subject, string $code): void;

    /**
     * Get the currently valid code for the given subject, if any.
     */
    public function get(string $subject): ?string;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function consume(string $subject, string $code, Closure $operation): mixed;

    /** Extend the live value only while it still equals the expected attempt. */
    public function extendTtlIfCurrent(string $subject, string $expected): bool;

    /** Replace a pending attempt only when no operation currently owns it. */
    public function replaceIfUnclaimed(string $subject, string $value): bool;

    /** Atomically issue an OTP and replace pending state when its cooldown has elapsed. */
    public function issueWithCooldownAndReplacePending(
        string $challengeSubject,
        string $code,
        string $cooldownSubject,
        string $pendingSubject,
        string $pendingValue,
    ): int;

    /**
     * Invalidate the code for the given subject (consumed or expired).
     */
    public function forget(string $subject): void;

    /**
     * Whether a new code may be requested for the given subject right now.
     */
    public function canBeRequested(string $subject): bool;

    /**
     * Seconds remaining before a new code may be requested.
     */
    public function secondsUntilNextRequest(string $subject): int;
}
