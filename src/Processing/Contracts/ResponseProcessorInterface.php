<?php

declare(strict_types=1);

namespace Maduser\Agent\Processing\Contracts;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Conversation\ConversationState;
use Maduser\Agent\Processing\ProcessedResponse;
use Maduser\Agent\LLM\LLMResponse;

interface ResponseProcessorInterface
{
    public function process(
        LLMResponse $response,
        ConversationState $conversationState,
        AgentContext $context,
    ): ProcessedResponse;
}
