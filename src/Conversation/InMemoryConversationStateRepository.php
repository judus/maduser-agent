<?php

declare(strict_types=1);

namespace Maduser\Agent\Conversation;

use Maduser\Agent\Conversation\Contracts\ConversationStateRepositoryInterface;

final class InMemoryConversationStateRepository implements ConversationStateRepositoryInterface
{
    /** @var array<string, ConversationState> */
    private array $states = [];

    #[\Override]
    public function load(string $conversationId): ConversationState
    {
        return $this->states[$conversationId] ?? new ConversationState();
    }

    #[\Override]
    public function save(string $conversationId, ConversationState $state): void
    {
        $this->states[$conversationId] = $state;
    }
}
