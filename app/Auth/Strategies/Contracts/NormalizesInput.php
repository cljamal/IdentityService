<?php

namespace App\Auth\Strategies\Contracts;

/**
 * Additional contract for strategies whose identifier needs canonicalizing
 * before it's validated/looked up (e.g. email case-folding), so that
 * "Foo@Bar.com" and "foo@bar.com" are treated as the same identity
 * regardless of the DB's collation.
 */
interface NormalizesInput
{
    public function normalize(array $data): array;
}
