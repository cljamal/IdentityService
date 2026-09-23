<?php

namespace App\Auth\Enums;

/**
 * Known role names, as a single source of truth for the string spatie
 * roles are keyed by — new registration contexts (contractor, ...) add a
 * case here plus their own event/listener pair rather than hardcoding a
 * role string at the call site.
 */
enum RoleName: string
{
    case User = 'user';
}
