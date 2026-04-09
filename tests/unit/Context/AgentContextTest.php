<?php

declare(strict_types=1);

namespace Tests\Unit\Context;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Conversation\InMemoryConversationStateRepository;
use PHPUnit\Framework\TestCase;

final class AgentContextTest extends TestCase
{
    public function testItCanBeCreated(): void
    {
        $context = new AgentContext(
            conversationStateRepository: new InMemoryConversationStateRepository(),
            id: 'test-agent',
            model: 'test-model',
        );

        self::assertSame('test-agent', $context->id);
    }

    public function testWithSystemPromptReturnsACopyWithUpdatedPrompt(): void
    {
        $context = new AgentContext(
            conversationStateRepository: new InMemoryConversationStateRepository(),
            systemPrompt: 'initial',
        );

        $updated = $context->withSystemPrompt('updated');

        self::assertSame('initial', $context->systemPrompt);
        self::assertSame('updated', $updated->systemPrompt);
        self::assertNotSame($context, $updated);
    }
}
