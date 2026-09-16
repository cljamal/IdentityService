<?php

namespace App\Repositories\Contracts;

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
