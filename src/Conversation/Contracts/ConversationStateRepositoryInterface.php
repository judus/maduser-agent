<?php

declare(strict_types=1);

namespace Maduser\Agent\Conversation\Contracts;

use Maduser\Agent\Conversation\ConversationState;

interface ConversationStateRepositoryInterface
{
    public function load(string $conversationId): ConversationState;

    public function save(string $conversationId, ConversationState $state): void;
}
