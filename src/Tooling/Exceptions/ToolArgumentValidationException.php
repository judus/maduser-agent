<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling\Exceptions;

use RuntimeException;

final class ToolArgumentValidationException extends RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(string $toolName, private readonly array $errors)
    {
        parent::__construct(
            sprintf(
                'Invalid arguments for tool "%s": %s',
                $toolName,
                implode('; ', $errors),
            ),
        );
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
