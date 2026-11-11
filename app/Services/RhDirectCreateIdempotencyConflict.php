<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;

final class RhDirectCreateIdempotencyConflict extends DomainException
{
}
