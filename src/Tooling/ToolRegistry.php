<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling;

use InvalidArgumentException;
use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\Contracts\ToolRegistryInterface;
use Maduser\Agent\Tooling\Exceptions\ToolNotFoundException;
use Maduser\Agent\Tooling\ToolDefinition;
use Psr\Container\ContainerInterface;
use ReflectionClass;

use function array_filter;
use function array_values;
use function class_exists;
use function in_array;
use function is_string;
use function is_subclass_of;

final class ToolRegistry implements ToolRegistryInterface
{
    /** @var array<string, ToolInterface|class-string<ToolInterface>> */
    private array $tools = [];

    public function __construct(
        private readonly ?ContainerInterface $container = null,
        private readonly ?ToolArgumentsValidator $validator = null,
    ) {
    }

    /**
     * @param ToolInterface|class-string<ToolInterface> $tool
     */
    public function addTool(ToolInterface|string $tool): self
    {
        if (is_string($tool)) {
            if (!class_exists($tool)) {
                throw new InvalidArgumentException("Tool class {$tool} does not exist.");
            }

            if (!is_subclass_of($tool, ToolInterface::class)) {
                throw new InvalidArgumentException(sprintf(
                    'Class %s must implement %s',
                    $tool,
                    ToolInterface::class,
                ));
            }

            $this->tools[$tool::name()] = $tool;

            return $this;
        }

        $this->tools[$tool::name()] = $tool;

        return $this;
    }

    /**
     * @param list<ToolInterface|class-string<ToolInterface>> $tools
     */
    public function addTools(array $tools): self
    {
        foreach ($tools as $tool) {
            $this->addTool($tool);
        }

        return $this;
    }

    /**
     * @return list<ToolInterface>
     */
    public function tools(): array
    {
        return array_values(array_map(
            fn (ToolInterface|string $tool): ToolInterface => $this->resolveTool($tool),
            $this->tools,
        ));
    }

    /**
     * @param list<string>|null $filter
     * @return list<ToolDefinition>
     */
    #[\Override]
    public function listTools(?array $filter = null): array
    {
        $tools = $this->tools();

        if ($filter !== null) {
            $tools = array_values(array_filter(
                $tools,
                static fn (ToolInterface $tool): bool => in_array($tool::name(), $filter, true),
            ));
        }

        return array_map(
            static fn (ToolInterface $tool): ToolDefinition => new ToolDefinition(
                name: $tool::name(),
                description: $tool::description(),
                parameters: $tool::parameters(),
            ),
            $tools,
        );
    }

    /**
     * @param array<string, mixed> $args
     */
    #[\Override]
    public function invoke(string $name, array $args, ?AgentContext $context = null): string|array
    {
        $registeredTool = $this->tools[$name] ?? null;

        if (!$registeredTool instanceof ToolInterface && !is_string($registeredTool)) {
            throw new ToolNotFoundException($name);
        }

        $tool = $this->resolveTool($registeredTool);
        $this->validator?->validate($name, $args, $tool::parameters());

        return $tool->execute($args, $context);
    }

    /**
     * @param ToolInterface|class-string<ToolInterface> $tool
     */
    private function resolveTool(ToolInterface|string $tool): ToolInterface
    {
        if ($tool instanceof ToolInterface) {
            return $tool;
        }

        if ($this->container instanceof ContainerInterface) {
            /** @var mixed $resolved */
            $resolved = $this->container->get($tool);

            if ($resolved instanceof ToolInterface) {
                return $resolved;
            }

            throw new InvalidArgumentException(sprintf(
                'Container resolved %s, but it does not implement %s.',
                $tool,
                ToolInterface::class,
            ));
        }

        $reflection = new ReflectionClass($tool);

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException(sprintf(
                'Tool class %s is not instantiable.',
                $tool,
            ));
        }

        /** @var ToolInterface $instance */
        $instance = $reflection->newInstance();

        return $instance;
    }
}
