<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling;

use JsonException;
use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\Contracts\ToolMiddlewareInterface;
use Maduser\Agent\Tooling\Contracts\ToolRegistryInterface;
use Maduser\Agent\Tooling\Exceptions\ToolArgumentValidationException;
use Maduser\Agent\Tooling\ToolCall;
use Maduser\Agent\Tooling\ToolResult;
use Throwable;

use function array_key_exists;
use function implode;
use function is_string;
use function json_encode;

final readonly class ToolExecutionPipeline
{
    /**
     * @param list<ToolMiddlewareInterface> $middleware
     */
    public function __construct(
        private ToolRegistryInterface $tools,
        private array $middleware = [],
        private ?AgentContext $context = null,
    ) {
    }

    public function withContext(AgentContext $context): self
    {
        return new self($this->tools, $this->middleware, $context);
    }

    public function getToolRegistry(): ToolRegistryInterface
    {
        return $this->tools;
    }

    /**
     * @throws JsonException
     */
    public function execute(ToolCall $call): ToolResult
    {
        $index = 0;

        /** @var callable(ToolCall): ToolResult $next */
        $next = function (ToolCall $call) use (&$index, &$next): ToolResult {
            if (!isset($this->middleware[$index])) {
                $args = $call->arguments();

                $rawOutput = $this->tools->invoke($call->name, $args, $this->context);
                $artifact = null;

                if (is_array($rawOutput) && array_key_exists('output', $rawOutput)) {
                    /** @var mixed $outputValue */
                    $outputValue = $rawOutput['output'] ?? '';
                    /** @var mixed $artifactValue */
                    $artifactValue = $rawOutput['artifact'] ?? null;

                    $output = is_string($outputValue)
                        ? $outputValue
                        : json_encode($outputValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if (is_array($artifactValue)) {
                        /** @var array<string, mixed> $artifact */
                        $artifact = $artifactValue;
                    }
                } else {
                    $output = is_string($rawOutput)
                        ? $rawOutput
                        : json_encode($rawOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }

                if ($output === false) {
                    $output = '';
                }

                return new ToolResult($call->id, $output, $artifact);
            }

            $middleware = $this->middleware[$index];
            $index++;

            /** @var callable(ToolCall): ToolResult $nextCallable */
            $nextCallable = $next;

            return $middleware->process($call, $nextCallable, $this->context);
        };

        try {
            return $next($call);
        } catch (ToolArgumentValidationException $exception) {
            return new ToolResult(
                $call->id,
                $this->formatArgumentValidationFailure($call->name, $exception),
            );
        } catch (Throwable $exception) {
            return new ToolResult(
                $call->id,
                $this->formatExecutionFailure($call->name, $exception),
            );
        }
    }

    private function formatArgumentValidationFailure(
        string $toolName,
        ToolArgumentValidationException $exception,
    ): string {
        return sprintf(
            'Tool "%s" rejected the arguments. '
            . 'If this task still matters, retry the same tool with corrected '
            . 'parameters that match its schema. Error: %s',
            $toolName,
            implode('; ', $exception->errors()),
        );
    }

    private function formatExecutionFailure(string $toolName, Throwable $exception): string
    {
        return sprintf(
            'Tool "%s" failed while executing. '
            . 'If the task is still needed, either retry with safer parameters '
            . 'or briefly inform the user that the tool is currently unavailable. '
            . 'Error: %s',
            $toolName,
            $exception->getMessage(),
        );
    }
}
