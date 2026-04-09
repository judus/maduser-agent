<?php

declare(strict_types=1);

namespace Maduser\Agent\Conversation;

use Maduser\Agent\LLM\Message\AssistantMessage;
use Maduser\Agent\LLM\Message\Contracts\MessageInterface;
use Maduser\Agent\LLM\Message\SystemMessage;
use Maduser\Agent\LLM\Message\UserMessage;
use Maduser\Agent\Support\HistoryTrimmer;
use Maduser\Agent\Tooling\ToolCall;
use Maduser\Agent\Tooling\ToolResult;

final readonly class ConversationState
{
    /**
     * @param list<MessageInterface> $history
     */
    public function __construct(
        public array $history = [],
        public string $summary = '',
    ) {
    }

    /**
     * @return list<MessageInterface>
     */
    public function getRecentMessages(int $maxTurns = 20): array
    {
        return HistoryTrimmer::safeTail($this->history, $maxTurns);
    }

    public function withSummary(string $summary): self
    {
        return new self(
            history: $this->history,
            summary: $summary,
        );
    }

    /**
     * @param list<MessageInterface> $history
     */
    public function withHistory(array $history): self
    {
        return new self(
            history: $history,
            summary: $this->summary,
        );
    }

    public function addSystemMessage(string $content): self
    {
        return $this->append(new SystemMessage($content));
    }

    public function addUserMessage(string $content, ?string $userId = null): self
    {
        return $this->append(new UserMessage($content, $userId));
    }

    /**
     * @param list<ToolCall>|null $toolCalls
     */
    public function addAssistantMessage(?string $content, ?array $toolCalls = null): self
    {
        return $this->append(new AssistantMessage($content ?? '', $toolCalls));
    }

    public function addToolResult(ToolResult $result): self
    {
        return $this->append($result);
    }

    public function append(MessageInterface $message): self
    {
        $history = $this->history;
        $history[] = $message;

        return new self(
            history: $history,
            summary: $this->summary,
        );
    }
}
