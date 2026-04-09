<?php

declare(strict_types=1);

namespace Maduser\Agent\Workflow;

use Maduser\Agent\Conversation\ConversationState;
use Maduser\Agent\Processing\ProcessedResponse;
use Maduser\Agent\LLM\LLMResponse;
use Maduser\Argon\Workflows\Contracts\ContextInterface;
use Override;
use Maduser\Agent\Context\AgentContext;

final class AgentWorkflowContext implements ContextInterface
{
    /**
     * @param list<array<string, mixed>> $trace
     * @param list<array<string, mixed>> $artifacts
     */
    public function __construct(
        public readonly AgentContext $agentContext,
        public readonly string $input,
        public readonly string $workflowId = 'default',
        private string $state = AgentState::Bootstrapping->value,
        public ?ConversationState $conversationState = null,
        public ?LLMResponse $response = null,
        public ?ProcessedResponse $processedResponse = null,
        public array $trace = [],
        public array $artifacts = [],
    ) {
    }

    #[Override]
    public function getState(): string
    {
        return $this->state;
    }

    #[Override]
    public function isComplete(): bool
    {
        return $this->state === AgentState::Done->value;
    }

    #[Override]
    public function withState(string $state): self
    {
        $clone = clone $this;
        $clone->state = $state;

        return $clone;
    }

    public function withResponse(LLMResponse $response): self
    {
        $clone = clone $this;
        $clone->response = $response;

        return $clone;
    }

    public function withConversationState(ConversationState $conversationState): self
    {
        $clone = clone $this;
        $clone->conversationState = $conversationState;

        return $clone;
    }

    public function withProcessedResponse(ProcessedResponse $processedResponse): self
    {
        $clone = clone $this;
        $clone->processedResponse = $processedResponse;

        return $clone;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function withTraceEntry(array $entry): self
    {
        $clone = clone $this;
        $clone->trace[] = $entry;

        return $clone;
    }

    /**
     * @param array<string, mixed> $artifact
     */
    public function withArtifact(array $artifact): self
    {
        $clone = clone $this;
        $clone->artifacts[] = $artifact;

        return $clone;
    }

    public function getFinalResponse(): ?LLMResponse
    {
        return $this->response;
    }
}
