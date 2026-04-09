<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\Contracts\ToolRegistryInterface;
use Maduser\Agent\Tooling\ToolDefinition;
use RuntimeException;

final class EmptyToolRegistry implements ToolRegistryInterface
{
    /**
     * @param list<string>|null $filter
     * @return list<ToolDefinition>
     */
    #[\Override]
    public function listTools(?array $filter = null): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $args
     */
    #[\Override]
    public function invoke(string $name, array $args, ?AgentContext $context = null): string|array
    {
        throw new RuntimeException('Tool is not registered: ' . $name);
    }
}
