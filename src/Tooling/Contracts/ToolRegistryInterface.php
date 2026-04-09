<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling\Contracts;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\ToolDefinition;

interface ToolRegistryInterface
{
    /**
     * @param list<string>|null $filter
     * @return list<ToolDefinition>
     */
    public function listTools(?array $filter = null): array;

    /**
     * @param array<string, mixed> $args
     */
    public function invoke(string $name, array $args, ?AgentContext $context = null): string|array;
}
