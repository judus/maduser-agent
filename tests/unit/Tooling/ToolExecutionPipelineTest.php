<?php

declare(strict_types=1);

namespace Tests\Unit\Tooling;

use JsonException;
use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Conversation\InMemoryConversationStateRepository;
use Maduser\Agent\Tooling\ToolCall;
use Maduser\Agent\Tooling\ToolDefinition;
use Maduser\Agent\Tooling\ToolArgumentsValidator;
use Maduser\Agent\Tooling\ToolExecutionPipeline;
use Maduser\Agent\Tooling\ToolInterface;
use Maduser\Agent\Tooling\ToolRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Support\Doubles\InMemoryToolRegistry;

final class ToolExecutionPipelineTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testItExecutesRegisteredToolsAndWrapsStructuredOutput(): void
    {
        $registry = new InMemoryToolRegistry([
            new ToolDefinition('lookup', 'Look something up'),
        ]);
        $registry->register('lookup', static fn (array $args): array => [
            'ok' => true,
            'query' => $args['query'] ?? null,
        ]);

        $pipeline = (new ToolExecutionPipeline($registry))->withContext(new AgentContext(
            conversationStateRepository: new InMemoryConversationStateRepository(),
            id: 'session-1',
        ));

        $result = $pipeline->execute(new ToolCall(
            id: 'call-1',
            name: 'lookup',
            argumentsJson: '{"query":"dragons"}',
        ));

        self::assertSame('call-1', $result->callId);
        self::assertJson($result->output);
        self::assertSame([
            'ok' => true,
            'query' => 'dragons',
        ], json_decode($result->output, true, 512, JSON_THROW_ON_ERROR));
        self::assertCount(1, $registry->invocations);
    }

    /**
     * @throws JsonException
     */
    public function testItFeedsArgumentValidationErrorsBackAsToolResults(): void
    {
        $registry = (new ToolRegistry(null, new ToolArgumentsValidator()))
            ->addTool(new class implements ToolInterface {
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
                            'query' => [
                                'type' => 'string',
                            ],
                        ],
                        'required' => ['query'],
                    ];
                }

                #[\Override]
                public function execute(array $args, ?AgentContext $context = null): string|array
                {
                    return 'ok';
                }
            });

        $pipeline = (new ToolExecutionPipeline($registry))->withContext(new AgentContext(
            conversationStateRepository: new InMemoryConversationStateRepository(),
            id: 'session-2',
        ));

        $result = $pipeline->execute(new ToolCall(
            id: 'call-2',
            name: 'lookup',
            argumentsJson: '{}',
        ));

        self::assertStringContainsString('rejected the arguments', $result->output);
        self::assertStringContainsString('retry the same tool with corrected parameters', $result->output);
        self::assertStringContainsString("Missing required field 'query'", $result->output);
    }

    /**
     * @throws JsonException
     */
    public function testItFeedsToolExecutionErrorsBackAsToolResults(): void
    {
        $registry = (new ToolRegistry())
            ->addTool(new class implements ToolInterface {
                #[\Override]
                public static function name(): string
                {
                    return 'explode';
                }

                #[\Override]
                public static function description(): string
                {
                    return 'Always fails';
                }

                #[\Override]
                public static function parameters(): array
                {
                    return [
                        'type' => 'object',
                        'properties' => [],
                        'required' => [],
                    ];
                }

                #[\Override]
                public function execute(array $args, ?AgentContext $context = null): string|array
                {
                    throw new \RuntimeException('Third-party API rejected the request.');
                }
            });

        $pipeline = (new ToolExecutionPipeline($registry))->withContext(new AgentContext(
            conversationStateRepository: new InMemoryConversationStateRepository(),
            id: 'session-3',
        ));

        $result = $pipeline->execute(new ToolCall(
            id: 'call-3',
            name: 'explode',
            argumentsJson: '{}',
        ));

        self::assertStringContainsString('failed while executing', $result->output);
        self::assertStringContainsString('tool is currently unavailable', $result->output);
        self::assertStringContainsString('Third-party API rejected the request.', $result->output);
    }
}
