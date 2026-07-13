<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a workflow action would violate a core inventory business rule
 * (approved > requested, issued > approved, issued > stock on hand, etc).
 * Callers (Filament actions) catch this and surface $e->getMessage() directly
 * as a user-facing notification.
 */
class InventoryRuleException extends RuntimeException
{
}
