<?php

declare(strict_types=1);

namespace App\Services\Import;

use RuntimeException;

/**
 * The file as a whole is unusable (header, size, empty); the message is safe to show to the administrator.
 */
final class ImportFileException extends RuntimeException {}
