<?php

namespace App\Repositories\Contracts;

interface OtpRepositoryInterface
{
    /**
     * Store the code for the given phone and start the resend cooldown.
     */
    public function put(string $phone, string $code): void;

    /**
     * Get the currently valid code for the given phone, if any.
     */
    public function get(string $phone): ?string;

    /**
     * Invalidate the code for the given phone (consumed or expired).
     */
    public function forget(string $phone): void;

    /**
     * Whether a new code may be requested for the given phone right now.
     */
    public function canBeRequested(string $phone): bool;

    /**
     * Seconds remaining before a new code may be requested.
     */
    public function secondsUntilNextRequest(string $phone): int;
}
