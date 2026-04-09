<?php

declare(strict_types=1);

namespace Tests\Unit;

use Maduser\Agent\Agent;
use Maduser\Agent\Conversation\InMemoryConversationStateRepository;
use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\ToolInterface;
use Maduser\Agent\Tooling\ToolRegistry;
use Maduser\Agent\LLM\LLMClient;
use Maduser\Agent\LLM\LLMResponse;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Tests\Support\Doubles\InMemoryLLMClient;
use Tests\Support\Fixtures\FixtureLookupTool;

final class AgentTest extends TestCase
{
    public function testItExposesThePackageName(): void
    {
        self::assertSame('maduser/agent', Agent::packageName());
    }

    public function testCreateDefaultProvidesABuiltInEmptyToolPipeline(): void
    {
        $llm = new LLMClient(
            new InMemoryLLMClient([
                new LLMResponse(role: 'assistant', content: 'Ready'),
            ]),
            'test-model',
        );

        $alix = Agent::createDefault($llm);

        $response = $alix
            ->withContext(new AgentContext(
                conversationStateRepository: new InMemoryConversationStateRepository(),
                id: 'session-1',
            ))
            ->ask('Hello');

        self::assertSame('Ready', $response->text);
        self::assertInstanceOf(LLMResponse::class, $response->response);
    }

    public function testCreateDefaultAcceptsAToolRegistryDirectly(): void
    {
        $registry = (new ToolRegistry())
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
                public function execute(array $args, ?\Maduser\Agent\Context\AgentContext $context = null): string|array
                {
                    /** @var mixed $query */
                    $query = $args['query'] ?? 'unknown';

                    return ['result' => 'Found: ' . (is_string($query) ? $query : 'unknown')];
                }
            });

        $alix = Agent::createDefault(
            new LLMClient(
                new InMemoryLLMClient([
                    new LLMResponse(role: 'assistant', content: '', toolCalls: [
                        new \Maduser\Agent\Tooling\ToolCall('call-1', 'lookup', '{"query":"Gerald"}'),
                    ]),
                    new LLMResponse(role: 'assistant', content: 'Gerald is ready.'),
                ]),
                'test-model',
            ),
            $registry,
        );

        $response = $alix->agent(
            llm: new LLMClient(
                new InMemoryLLMClient([
                    new LLMResponse(role: 'assistant', content: '', toolCalls: [
                        new \Maduser\Agent\Tooling\ToolCall('call-1', 'lookup', '{"query":"Gerald"}'),
                    ]),
                    new LLMResponse(role: 'assistant', content: 'Gerald is ready.'),
                ]),
                'test-model',
            ),
            context: new AgentContext(
                conversationStateRepository: new InMemoryConversationStateRepository(),
                id: 'session-2',
            ),
            tools: $registry,
        )->query('Who is Gerald?');

        self::assertSame('Gerald is ready.', $response->text);
    }

    public function testAgentCanBuildAPerRunRegistryFromToolClasses(): void
    {
        $container = new class (FixtureLookupTool::class) implements ContainerInterface {
            public function __construct(
                private readonly string $toolClass,
            ) {
            }

            #[\Override]
            public function get(string $id): mixed
            {
                if ($id === $this->toolClass) {
                    return new FixtureLookupTool();
                }

                throw new \RuntimeException('Unknown service: ' . $id);
            }

            #[\Override]
            public function has(string $id): bool
            {
                return $id === $this->toolClass;
            }
        };

        $llm = new LLMClient(
            new InMemoryLLMClient([
                new LLMResponse(role: 'assistant', content: '', toolCalls: [
                    new \Maduser\Agent\Tooling\ToolCall('call-1', 'lookup', '{"query":"Gerald"}'),
                ]),
                new LLMResponse(role: 'assistant', content: 'Gerald is ready.'),
            ]),
            'test-model',
        );

        $alix = Agent::createDefault($llm, null, $container);

        $response = $alix->agent(
            llm: $llm,
            context: new AgentContext(
                conversationStateRepository: new InMemoryConversationStateRepository(),
                id: 'session-3',
            ),
        )->withTools([
            FixtureLookupTool::class,
        ])->query('Who is Gerald?');

        self::assertSame('Gerald is ready.', $response->text);
    }

    public function testItSupportsDirectAskAfterConfiguringContextAndWorkflow(): void
    {
        $llm = new LLMClient(
            new InMemoryLLMClient([
                new LLMResponse(role: 'assistant', content: 'Summary ready.'),
            ]),
            'test-model',
        );

        $alix = Agent::createDefault($llm);

        $response = $alix
            ->withContext(new AgentContext(
                conversationStateRepository: new InMemoryConversationStateRepository(),
                id: 'session-4',
            ))
            ->withWorkflow('default')
            ->ask('Summarize courage.');

        self::assertSame('Summary ready.', $response->text);
    }

    public function testItCanRespondFromSeededHistoryWithoutAddingAUserTurn(): void
    {
        $repository = new InMemoryConversationStateRepository();
        $repository->save('session-5', (new \Maduser\Agent\Conversation\ConversationState())
            ->addUserMessage('Hello there.'));

        $llm = new LLMClient(
            new InMemoryLLMClient([
                new LLMResponse(role: 'assistant', content: 'General Kenobi.'),
            ]),
            'test-model',
        );

        $alix = Agent::createDefault($llm);

        $response = $alix
            ->withContext(new AgentContext(
                conversationStateRepository: $repository,
                id: 'session-5',
            ))
            ->respond();
        $state = $repository->load('session-5');

        self::assertSame('General Kenobi.', $response->text);
        self::assertCount(2, $state->history);
    }

    public function testItSummarizesOverflowDuringFinalizing(): void
    {
        $repository = new InMemoryConversationStateRepository();
        $repository->save('session-6', (new \Maduser\Agent\Conversation\ConversationState())
            ->addUserMessage('Older user message.')
            ->addAssistantMessage('Older assistant reply.')
            ->addUserMessage('Another older user message.')
            ->addAssistantMessage('Another older assistant reply.'));

        $llm = new LLMClient(
            new InMemoryLLMClient([
                new LLMResponse(role: 'assistant', content: 'Fresh reply.'),
                new LLMResponse(role: 'assistant', content: 'Compact summary of earlier turns.'),
            ]),
            'test-model',
        );

        $alix = Agent::createDefault($llm);

        $response = $alix
            ->withContext(new AgentContext(
                conversationStateRepository: $repository,
                id: 'session-6',
                summaryAfter: 4,
            ))
            ->ask('Newest message.');
        $state = $repository->load('session-6');

        self::assertSame('Fresh reply.', $response->text);
        self::assertSame('Compact summary of earlier turns.', $state->summary);
        self::assertLessThan(6, count($state->history));
    }
}
