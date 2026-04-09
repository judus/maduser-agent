<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling;

use InvalidArgumentException;
use Maduser\Agent\Tooling\Exceptions\ToolArgumentValidationException;
use stdClass;

use function array_key_exists;
use function get_debug_type;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;

final class ToolArgumentsValidator
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $schema
     *
     * @throws ToolArgumentValidationException
     */
    public function validate(string $toolName, array $arguments, array $schema): void
    {
        $errors = [];

        if (($schema['type'] ?? null) !== 'object') {
            throw new InvalidArgumentException("Only 'object' tool schemas are supported.");
        }

        /** @var mixed $rawProperties */
        $rawProperties = $schema['properties'] ?? [];

        if ($rawProperties instanceof stdClass) {
            $properties = (array) $rawProperties;
        } elseif (is_array($rawProperties)) {
            $properties = $rawProperties;
        } else {
            throw new InvalidArgumentException(
                "Tool schema 'properties' must be an array or stdClass.",
            );
        }

        /** @var list<string> $required */
        $required = $schema['required'] ?? [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $arguments)) {
                $errors[] = sprintf("Missing required field '%s'", $field);
            }
        }

        /** @psalm-suppress MixedAssignment */
        foreach ($arguments as $key => $value) {
            /** @var mixed $value */
            $definition = $properties[$key] ?? null;

            if (!is_array($definition)) {
                continue;
            }

            $expected = $definition['type'] ?? null;

            if (!is_string($expected)) {
                continue;
            }

            $isValid = match ($expected) {
                'string' => is_string($value),
                'int', 'integer' => is_int($value),
                'float', 'number' => is_float($value) || is_int($value),
                'bool', 'boolean' => is_bool($value),
                'array' => is_array($value),
                'object' => is_array($value) || is_object($value),
                default => true,
            };

            if (!$isValid) {
                $errors[] = sprintf(
                    "Invalid type for '%s': expected %s, got %s",
                    $key,
                    $expected,
                    get_debug_type($value),
                );
            }
        }

        if ($errors !== []) {
            throw new ToolArgumentValidationException($toolName, $errors);
        }
    }
}
