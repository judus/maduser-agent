<?php

declare(strict_types=1);

namespace Maduser\Agent\Processing;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Conversation\ConversationState;
use Maduser\Agent\Processing\Contracts\ResponseProcessorInterface;
use Maduser\Agent\LLM\LLMResponse;

final class DefaultResponseProcessor implements ResponseProcessorInterface
{
    #[\Override]
    public function process(
        LLMResponse $response,
        ConversationState $conversationState,
        AgentContext $context,
    ): ProcessedResponse {
        $assistantMessage = $response->content;

        return new ProcessedResponse(
            conversationState: $conversationState->addAssistantMessage($assistantMessage),
            assistantMessage: $assistantMessage,
        );
    }
}
