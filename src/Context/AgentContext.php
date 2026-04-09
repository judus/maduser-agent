<?php

declare(strict_types=1);

namespace Maduser\Agent\Context;

use Maduser\Agent\Conversation\Contracts\ConversationStateRepositoryInterface;

final readonly class AgentContext
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $vendorHints
     * @param list<string>|null $tools
     */
    public function __construct(
        public ConversationStateRepositoryInterface $conversationStateRepository,
        public string $id = 'default_agent',
        public ?string $model = null,
        public ?string $systemPrompt = null,
        public array $options = [],
        public array $vendorHints = [],
        public ?array $tools = null,
        public ?int $maxMessages = 20,
        public ?int $summaryAfter = 20,
    ) {
    }

    public function withConversationStateRepository(
        ConversationStateRepositoryInterface $conversationStateRepository,
    ): self {
        return new self(
            conversationStateRepository: $conversationStateRepository,
            id: $this->id,
            model: $this->model,
            systemPrompt: $this->systemPrompt,
            options: $this->options,
            vendorHints: $this->vendorHints,
            tools: $this->tools,
            maxMessages: $this->maxMessages,
            summaryAfter: $this->summaryAfter,
        );
    }

    public function withSystemPrompt(?string $systemPrompt): self
    {
        return new self(
            conversationStateRepository: $this->conversationStateRepository,
            id: $this->id,
            model: $this->model,
            systemPrompt: $systemPrompt,
            options: $this->options,
            vendorHints: $this->vendorHints,
            tools: $this->tools,
            maxMessages: $this->maxMessages,
            summaryAfter: $this->summaryAfter,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): self
    {
        return new self(
            conversationStateRepository: $this->conversationStateRepository,
            id: $this->id,
            model: $this->model,
            systemPrompt: $this->systemPrompt,
            options: $options,
            vendorHints: $this->vendorHints,
            tools: $this->tools,
            maxMessages: $this->maxMessages,
            summaryAfter: $this->summaryAfter,
        );
    }
}
