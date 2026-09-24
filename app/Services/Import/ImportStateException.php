<?php

declare(strict_types=1);

namespace App\Services\Import;

use RuntimeException;

/**
 * The batch is not in a state that allows the action (already committed, discarded, or changed since the dry run);
 * the message is safe to show to the administrator.
 */
final class ImportStateException extends RuntimeException {}
