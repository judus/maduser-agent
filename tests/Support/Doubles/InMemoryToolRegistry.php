<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\Contracts\ToolRegistryInterface;
use Maduser\Agent\Tooling\ToolDefinition;

use function array_filter;
use function array_values;
use function in_array;

final class InMemoryToolRegistry implements ToolRegistryInterface
{
    /**
     * @var array<string, callable(array<string, mixed>, ?AgentContext): array<string, mixed>|string>
     */
    private array $handlers = [];

    /**
     * @var list<array{name: string, args: array<string, mixed>, context: ?AgentContext}>
     */
    public array $invocations = [];

    /**
     * @param list<ToolDefinition> $definitions
     */
    public function __construct(
        private readonly array $definitions = [],
    ) {
    }

    public function register(string $toolName, callable $handler): void
    {
        /** @var callable(array<string, mixed>, ?AgentContext): array<string, mixed>|string $typedHandler */
        $typedHandler = $handler;

        $this->handlers[$toolName] = $typedHandler;
    }

    #[\Override]
    public function listTools(?array $filter = null): array
    {
        if ($filter === null) {
            return $this->definitions;
        }

        return array_values(array_filter(
            $this->definitions,
            static fn (ToolDefinition $definition): bool => in_array($definition->name, $filter, true),
        ));
    }

    #[\Override]
    public function invoke(string $name, array $args, ?AgentContext $context = null): string|array
    {
        $this->invocations[] = [
            'name' => $name,
            'args' => $args,
            'context' => $context,
        ];

        return ($this->handlers[$name])($args, $context);
    }
}
