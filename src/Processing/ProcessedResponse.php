<?php

declare(strict_types=1);

namespace Maduser\Agent\Processing;

use Maduser\Agent\Conversation\ConversationState;

final readonly class ProcessedResponse
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public ConversationState $conversationState,
        public string $assistantMessage,
        public array $meta = [],
    ) {
    }
}
