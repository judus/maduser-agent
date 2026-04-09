<?php

declare(strict_types=1);

namespace Maduser\Agent\Workflow\Handlers;

use Maduser\Agent\Workflow\AgentWorkflowContext;
use Maduser\Argon\Workflows\Contracts\ContextInterface;
use Maduser\Argon\Workflows\Contracts\StateHandlerInterface;
use Maduser\Argon\Workflows\HandlerResult;
use Override;

final class StoringHandler implements StateHandlerInterface
{
    #[Override]
    public function handle(ContextInterface $context): HandlerResult
    {
        if (!$context instanceof AgentWorkflowContext) {
            return new HandlerResult($context);
        }

        $state = $context->conversationState
            ?? $context->agentContext->conversationStateRepository->load($context->agentContext->id);

        $context->agentContext->conversationStateRepository->save(
            $context->agentContext->id,
            $state,
        );

        return new HandlerResult($context->withConversationState($state));
    }
}
