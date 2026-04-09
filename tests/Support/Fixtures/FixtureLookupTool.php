<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\ToolInterface;

use function is_string;

final class FixtureLookupTool implements ToolInterface
{
    #[\Override]
    public static function name(): string
    {
        return 'lookup';
    }

    #[\Override]
    public static function description(): string
    {
        return 'Lookup data';
    }

    #[\Override]
    public static function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
            ],
            'required' => ['query'],
        ];
    }

    #[\Override]
    public function execute(array $args, ?AgentContext $context = null): string|array
    {
        /** @var mixed $query */
        $query = $args['query'] ?? 'unknown';

        return ['result' => 'Found: ' . (is_string($query) ? $query : 'unknown')];
    }
}
