<?php

declare(strict_types=1);

namespace App\Tour\Domain;

use RuntimeException;

final class StepNotFoundException extends \RuntimeException
{
    public static function forId(string $stepId): self
    {
        return new self(sprintf('Tour step "%s" not found', $stepId));
    }
}
