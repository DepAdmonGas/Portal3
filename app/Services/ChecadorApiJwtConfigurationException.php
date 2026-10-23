<?php

declare(strict_types=1);

namespace App\Services;

/** Safe, non-secret configuration failure used to stop before HTTP dispatch. */
final class ChecadorApiJwtConfigurationException extends \RuntimeException
{
}
