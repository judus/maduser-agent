<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling\Exceptions;

use RuntimeException;

final class ToolNotFoundException extends RuntimeException
{
    public function __construct(string $toolName)
    {
        parent::__construct(sprintf('Tool "%s" is not registered.', $toolName));
    }
}
